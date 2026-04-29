import * as mediasoup from 'mediasoup';

import { mediasoupConfig } from './config.mjs';
import { mediaCodecs } from './mediaCodecs.mjs';

let workerPromise;

async function getWorker() {
    if (!workerPromise) {
        workerPromise = mediasoup.createWorker({
            logLevel: mediasoupConfig.logLevel,
            rtcMinPort: mediasoupConfig.rtcMinPort,
            rtcMaxPort: mediasoupConfig.rtcMaxPort,
        });
    }

    return workerPromise;
}

export class RoomRegistry {
    constructor() {
        this.rooms = new Map();
    }

    async getOrCreateRoom(roomSlug) {
        let room = this.rooms.get(roomSlug);

        if (room) {
            return room;
        }

        const worker = await getWorker();
        const router = await worker.createRouter({ mediaCodecs });

        room = {
            slug: roomSlug,
            router,
            peers: new Map(),
        };

        this.rooms.set(roomSlug, room);

        return room;
    }

    async createWebRtcTransport(room, peerId, direction) {
        const transport = await room.router.createWebRtcTransport({
            listenInfos: [
                {
                    protocol: 'udp',
                    ip: mediasoupConfig.rtcListenIp,
                    announcedAddress: mediasoupConfig.rtcAnnouncedAddress,
                },
            ],
            enableSctp: true,
            numSctpStreams: { OS: 1024, MIS: 1024 },
            appData: { peerId, direction },
        });

        return transport;
    }

    closePeer(roomSlug, peerId) {
        const room = this.rooms.get(roomSlug);

        if (!room) {
            return;
        }

        const peer = room.peers.get(peerId);

        if (!peer) {
            return;
        }

        for (const transport of peer.transports.values()) {
            transport.close();
        }

        for (const producer of peer.producers.values()) {
            producer.close();
        }

        for (const consumer of peer.consumers.values()) {
            consumer.close();
        }

        for (const dataProducer of peer.dataProducers.values()) {
            dataProducer.close();
        }

        for (const dataConsumer of peer.dataConsumers.values()) {
            dataConsumer.close();
        }

        room.peers.delete(peerId);

        if (room.peers.size === 0) {
            room.router.close();
            this.rooms.delete(roomSlug);
        }
    }
}
