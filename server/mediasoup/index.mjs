import { createServer } from 'node:http';

import { WebSocketServer } from 'ws';

import { mediasoupConfig } from './config.mjs';
import { RoomRegistry } from './roomRegistry.mjs';
import { verifyRoomToken } from './token.mjs';

const registry = new RoomRegistry();

const httpServer = createServer((request, response) => {
    response.writeHead(200, { 'content-type': 'application/json' });
    response.end(JSON.stringify({ ok: true, service: 'mediasoup-signaling' }));
});

const wss = new WebSocketServer({ server: httpServer });

function send(ws, payload) {
    if (ws.readyState === ws.OPEN) {
        ws.send(JSON.stringify(payload));
    }
}

function reply(ws, requestId, data) {
    send(ws, { requestId, ok: true, data });
}

function fail(ws, requestId, error) {
    send(ws, {
        requestId,
        ok: false,
        error: error instanceof Error ? error.message : String(error),
    });
}

function notifyPeers(room, currentPeerId, payload) {
    for (const [peerId, peer] of room.peers.entries()) {
        if (peerId === currentPeerId) {
            continue;
        }

        send(peer.ws, { notification: true, ...payload });
    }
}

function publicPeer(peer) {
    return {
        id: peer.id,
        displayName: peer.displayName,
        role: peer.role,
    };
}

function ensurePeer(ws) {
    if (!ws.peerState) {
        throw new Error('Client must join a room before performing this action');
    }

    return ws.peerState;
}

async function handleJoinRoom(ws, requestId, data) {
    const payload = verifyRoomToken(data.token, mediasoupConfig.callbackSecret);

    if (data.roomSlug && data.roomSlug !== payload.room_slug) {
        throw new Error('Room token does not match requested room slug');
    }

    const room = await registry.getOrCreateRoom(payload.room_slug);

    const peer = {
        id: String(payload.session_id),
        roomSlug: room.slug,
        displayName: payload.display_name,
        role: payload.role,
        permissions: payload.permissions || {},
        ws,
        transports: new Map(),
        producers: new Map(),
        consumers: new Map(),
        dataProducers: new Map(),
        dataConsumers: new Map(),
    };

    room.peers.set(peer.id, peer);
    ws.peerState = peer;

    notifyPeers(room, peer.id, {
        type: 'peerJoined',
        peer: publicPeer(peer),
    });

    reply(ws, requestId, {
        peerId: peer.id,
        roomSlug: room.slug,
        routerRtpCapabilities: room.router.rtpCapabilities,
        peers: [...room.peers.values()]
            .filter((existingPeer) => existingPeer.id !== peer.id)
            .map(publicPeer),
    });
}

async function handleCreateTransport(ws, requestId, data) {
    const peer = ensurePeer(ws);
    const room = await registry.getOrCreateRoom(peer.roomSlug);
    const transport = await registry.createWebRtcTransport(room, peer.id, data.direction || 'send');

    peer.transports.set(transport.id, transport);

    reply(ws, requestId, {
        id: transport.id,
        iceParameters: transport.iceParameters,
        iceCandidates: transport.iceCandidates,
        dtlsParameters: transport.dtlsParameters,
        sctpParameters: transport.sctpParameters,
    });
}

async function handleConnectTransport(ws, requestId, data) {
    const peer = ensurePeer(ws);
    const transport = peer.transports.get(data.transportId);

    if (!transport) {
        throw new Error('Transport not found');
    }

    await transport.connect({ dtlsParameters: data.dtlsParameters });

    reply(ws, requestId, { connected: true });
}

async function handleProduce(ws, requestId, data) {
    const peer = ensurePeer(ws);
    const room = await registry.getOrCreateRoom(peer.roomSlug);
    const transport = peer.transports.get(data.transportId);

    if (!transport) {
        throw new Error('Transport not found');
    }

    const producer = await transport.produce({
        kind: data.kind,
        rtpParameters: data.rtpParameters,
        appData: data.appData || {},
    });

    peer.producers.set(producer.id, producer);

    producer.on('transportclose', () => {
        peer.producers.delete(producer.id);
    });

    notifyPeers(room, peer.id, {
        type: 'newProducer',
        producerId: producer.id,
        peerId: peer.id,
        kind: producer.kind,
    });

    reply(ws, requestId, { id: producer.id });
}

async function handleConsume(ws, requestId, data) {
    const peer = ensurePeer(ws);
    const room = await registry.getOrCreateRoom(peer.roomSlug);
    const transport = peer.transports.get(data.transportId);

    if (!transport) {
        throw new Error('Transport not found');
    }

    let targetProducer;

    for (const roomPeer of room.peers.values()) {
        targetProducer = roomPeer.producers.get(data.producerId);

        if (targetProducer) {
            break;
        }
    }

    if (!targetProducer) {
        throw new Error('Producer not found');
    }

    if (!room.router.canConsume({ producerId: targetProducer.id, rtpCapabilities: data.rtpCapabilities })) {
        throw new Error('Router cannot consume this producer with the provided RTP capabilities');
    }

    const consumer = await transport.consume({
        producerId: targetProducer.id,
        rtpCapabilities: data.rtpCapabilities,
        paused: true,
    });

    peer.consumers.set(consumer.id, consumer);

    consumer.on('transportclose', () => {
        peer.consumers.delete(consumer.id);
    });

    consumer.on('producerclose', () => {
        peer.consumers.delete(consumer.id);
        send(ws, {
            notification: true,
            type: 'consumerClosed',
            consumerId: consumer.id,
            producerId: targetProducer.id,
        });
    });

    reply(ws, requestId, {
        id: consumer.id,
        producerId: targetProducer.id,
        kind: consumer.kind,
        rtpParameters: consumer.rtpParameters,
    });
}

async function handleResumeConsumer(ws, requestId, data) {
    const peer = ensurePeer(ws);
    const consumer = peer.consumers.get(data.consumerId);

    if (!consumer) {
        throw new Error('Consumer not found');
    }

    await consumer.resume();

    reply(ws, requestId, { resumed: true });
}

async function handleProduceData(ws, requestId, data) {
    const peer = ensurePeer(ws);
    const room = await registry.getOrCreateRoom(peer.roomSlug);
    const transport = peer.transports.get(data.transportId);

    if (!transport) {
        throw new Error('Transport not found');
    }

    const dataProducer = await transport.produceData({
        sctpStreamParameters: data.sctpStreamParameters,
        label: data.label,
        protocol: data.protocol,
        appData: data.appData || {},
    });

    peer.dataProducers.set(dataProducer.id, dataProducer);

    dataProducer.on('transportclose', () => {
        peer.dataProducers.delete(dataProducer.id);
    });

    notifyPeers(room, peer.id, {
        type: 'newDataProducer',
        dataProducerId: dataProducer.id,
        peerId: peer.id,
        label: dataProducer.label,
    });

    reply(ws, requestId, { id: dataProducer.id });
}

async function handleConsumeData(ws, requestId, data) {
    const peer = ensurePeer(ws);
    const transport = peer.transports.get(data.transportId);

    if (!transport) {
        throw new Error('Transport not found');
    }

    const room = await registry.getOrCreateRoom(peer.roomSlug);
    let targetDataProducer;

    for (const roomPeer of room.peers.values()) {
        targetDataProducer = roomPeer.dataProducers.get(data.dataProducerId);

        if (targetDataProducer) {
            break;
        }
    }

    if (!targetDataProducer) {
        throw new Error('Data producer not found');
    }

    const dataConsumer = await transport.consumeData({
        dataProducerId: targetDataProducer.id,
    });

    peer.dataConsumers.set(dataConsumer.id, dataConsumer);

    dataConsumer.on('transportclose', () => {
        peer.dataConsumers.delete(dataConsumer.id);
    });

    reply(ws, requestId, {
        id: dataConsumer.id,
        dataProducerId: targetDataProducer.id,
        sctpStreamParameters: dataConsumer.sctpStreamParameters,
        label: dataConsumer.label,
        protocol: dataConsumer.protocol,
    });
}

async function handleMessage(ws, rawMessage) {
    let payload;

    try {
        payload = JSON.parse(rawMessage.toString());
    } catch {
        fail(ws, null, 'Invalid JSON payload');

        return;
    }

    const { action, data = {}, requestId = null } = payload;

    try {
        switch (action) {
            case 'joinRoom':
                await handleJoinRoom(ws, requestId, data);
                break;
            case 'createWebRtcTransport':
                await handleCreateTransport(ws, requestId, data);
                break;
            case 'connectWebRtcTransport':
                await handleConnectTransport(ws, requestId, data);
                break;
            case 'produce':
                await handleProduce(ws, requestId, data);
                break;
            case 'consume':
                await handleConsume(ws, requestId, data);
                break;
            case 'resumeConsumer':
                await handleResumeConsumer(ws, requestId, data);
                break;
            case 'produceData':
                await handleProduceData(ws, requestId, data);
                break;
            case 'consumeData':
                await handleConsumeData(ws, requestId, data);
                break;
            default:
                throw new Error(`Unsupported action: ${action}`);
        }
    } catch (error) {
        fail(ws, requestId, error);
    }
}

wss.on('connection', (ws) => {
    ws.on('message', (message) => {
        handleMessage(ws, message);
    });

    ws.on('close', () => {
        if (!ws.peerState) {
            return;
        }

        const { roomSlug, id } = ws.peerState;
        const room = registry.rooms.get(roomSlug);

        if (room) {
            notifyPeers(room, id, {
                type: 'peerLeft',
                peerId: id,
            });
        }

        registry.closePeer(roomSlug, id);
    });
});

httpServer.listen(mediasoupConfig.listenPort, mediasoupConfig.listenHost, () => {
    console.log(
        `mediasoup signaling listening on ws://${mediasoupConfig.listenHost}:${mediasoupConfig.listenPort}`
    );
});
