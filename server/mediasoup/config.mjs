import process from 'node:process';

export const mediasoupConfig = {
    listenHost: process.env.MEDIASOUP_LISTEN_HOST || '127.0.0.1',
    listenPort: Number(process.env.MEDIASOUP_LISTEN_PORT || 4010),
    rtcListenIp: process.env.MEDIASOUP_RTC_LISTEN_IP || '127.0.0.1',
    rtcAnnouncedAddress: process.env.MEDIASOUP_RTC_ANNOUNCED_ADDRESS || undefined,
    rtcMinPort: Number(process.env.MEDIASOUP_RTC_MIN_PORT || 40000),
    rtcMaxPort: Number(process.env.MEDIASOUP_RTC_MAX_PORT || 40199),
    logLevel: process.env.MEDIASOUP_LOG_LEVEL || 'warn',
    callbackSecret: process.env.MEDIA_WORKER_CALLBACK_SECRET || process.env.APP_KEY || 'local-secret',
};
