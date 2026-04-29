import { Device } from 'mediasoup-client';
import type {
    Consumer,
    Device as MediasoupDevice,
    Producer,
    Transport,
    TransportOptions,
} from 'mediasoup-client/types';
import { ref } from 'vue';

type SignalingPayload = {
    room_slug: string;
    signaling_url: string;
    token: string;
};

type PeerSummary = {
    id: string;
    displayName: string;
    role: string;
};

type PendingRequest = {
    resolve: (value: unknown) => void;
    reject: (reason?: unknown) => void;
};

type ConsumerRecord = {
    id: string;
    producerId: string;
    consumer: Consumer;
    stream: MediaStream;
};

export function useGuestMedia() {
    const connected = ref(false);
    const peers = ref<PeerSummary[]>([]);
    const error = ref<string | null>(null);
    const localStream = ref<MediaStream | null>(null);
    const remoteStreams = ref<ConsumerRecord[]>([]);

    const pendingRequests = new Map<string, PendingRequest>();

    let socket: WebSocket | null = null;
    let device: MediasoupDevice | null = null;
    let sendTransport: Transport | null = null;
    let recvTransport: Transport | null = null;
    let requestCounter = 0;

    function nextRequestId() {
        requestCounter += 1;

        return `req_${requestCounter}`;
    }

    function request(action: string, data: Record<string, unknown> = {}) {
        if (!socket) {
            return Promise.reject(
                new Error('Guest media signaling socket is not connected'),
            );
        }

        const requestId = nextRequestId();

        return new Promise((resolve, reject) => {
            pendingRequests.set(requestId, { resolve, reject });
            socket?.send(JSON.stringify({ action, data, requestId }));
        });
    }

    async function createDevice(
        routerRtpCapabilities: Record<string, unknown>,
    ) {
        device = await Device.factory();
        await device.load({ routerRtpCapabilities });
    }

    async function setupTransport(direction: 'send' | 'recv') {
        if (!device) {
            throw new Error('Mediasoup device not initialized');
        }

        const transportOptions = (await request('createWebRtcTransport', {
            direction,
        })) as TransportOptions;

        const transport =
            direction === 'send'
                ? device.createSendTransport(transportOptions)
                : device.createRecvTransport(transportOptions);

        transport.on(
            'connect',
            async ({ dtlsParameters }, callback, errback) => {
                try {
                    await request('connectWebRtcTransport', {
                        transportId: transport.id,
                        dtlsParameters,
                    });
                    callback();
                } catch (transportError) {
                    errback(transportError as Error);
                }
            },
        );

        if (direction === 'send') {
            transport.on(
                'produce',
                async ({ kind, rtpParameters, appData }, callback, errback) => {
                    try {
                        const response = (await request('produce', {
                            transportId: transport.id,
                            kind,
                            rtpParameters,
                            appData,
                        })) as { id: string };
                        callback({ id: response.id });
                    } catch (transportError) {
                        errback(transportError as Error);
                    }
                },
            );
        }

        return transport;
    }

    async function subscribeToProducer(producerId: string) {
        if (!device || !recvTransport) {
            return;
        }

        const response = (await request('consume', {
            transportId: recvTransport.id,
            producerId,
            rtpCapabilities: device.rtpCapabilities,
        })) as {
            id: string;
            producerId: string;
            kind: 'audio' | 'video';
            rtpParameters: Record<string, unknown>;
        };

        const consumer = await recvTransport.consume({
            id: response.id,
            producerId: response.producerId,
            kind: response.kind,
            rtpParameters: response.rtpParameters as never,
        } as never);

        const stream = new MediaStream([consumer.track]);

        remoteStreams.value = [
            ...remoteStreams.value.filter(
                (record) => record.id !== consumer.id,
            ),
            { id: consumer.id, producerId, consumer, stream },
        ];

        await request('resumeConsumer', {
            consumerId: consumer.id,
        });
    }

    async function startLocalMedia() {
        if (!sendTransport) {
            throw new Error('Send transport not ready');
        }

        localStream.value = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: true,
        });

        const tracks = localStream.value.getTracks();
        const producers: Producer[] = [];

        for (const track of tracks) {
            const producer = await sendTransport.produce({ track });
            producers.push(producer);
        }

        return producers;
    }

    function handleNotification(payload: Record<string, unknown>) {
        const type = payload.type;

        if (type === 'peerJoined') {
            peers.value = [...peers.value, payload.peer as PeerSummary];
        }

        if (type === 'peerLeft') {
            peers.value = peers.value.filter(
                (peer) => peer.id !== payload.peerId,
            );
            remoteStreams.value = remoteStreams.value.filter(
                (record) => record.producerId !== payload.peerId,
            );
        }

        if (type === 'newProducer') {
            void subscribeToProducer(String(payload.producerId));
        }

        if (type === 'consumerClosed') {
            remoteStreams.value = remoteStreams.value.filter(
                (record) => record.id !== payload.consumerId,
            );
        }
    }

    async function connectToRoom(signaling: SignalingPayload) {
        error.value = null;

        socket = new WebSocket(signaling.signaling_url);

        await new Promise<void>((resolve, reject) => {
            socket?.addEventListener('open', () => resolve(), { once: true });
            socket?.addEventListener(
                'error',
                () =>
                    reject(
                        new Error('Unable to connect to mediasoup signaling'),
                    ),
                { once: true },
            );
        });

        socket.addEventListener('message', (event) => {
            const payload = JSON.parse(event.data as string) as {
                requestId?: string;
                ok?: boolean;
                data?: unknown;
                error?: string;
                notification?: boolean;
                type?: string;
            };

            if (payload.notification) {
                handleNotification(payload as Record<string, unknown>);

                return;
            }

            if (!payload.requestId) {
                return;
            }

            const pending = pendingRequests.get(payload.requestId);

            if (!pending) {
                return;
            }

            pendingRequests.delete(payload.requestId);

            if (payload.ok) {
                pending.resolve(payload.data);

                return;
            }

            pending.reject(
                new Error(payload.error || 'Unknown mediasoup signaling error'),
            );
        });

        const joinResponse = (await request('joinRoom', {
            roomSlug: signaling.room_slug,
            token: signaling.token,
        })) as {
            peers: PeerSummary[];
            routerRtpCapabilities: Record<string, unknown>;
        };

        peers.value = joinResponse.peers;

        await createDevice(joinResponse.routerRtpCapabilities);
        sendTransport = await setupTransport('send');
        recvTransport = await setupTransport('recv');
        connected.value = true;
    }

    async function disconnect() {
        connected.value = false;

        for (const remoteStream of remoteStreams.value) {
            remoteStream.consumer.close();
        }

        remoteStreams.value = [];
        localStream.value?.getTracks().forEach((track) => track.stop());
        localStream.value = null;
        sendTransport?.close();
        recvTransport?.close();
        socket?.close();
        socket = null;
        device = null;
        sendTransport = null;
        recvTransport = null;
        pendingRequests.clear();
    }

    return {
        connected,
        peers,
        error,
        localStream,
        remoteStreams,
        connectToRoom,
        startLocalMedia,
        subscribeToProducer,
        disconnect,
    };
}
