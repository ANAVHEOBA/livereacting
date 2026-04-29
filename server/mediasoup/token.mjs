import crypto from 'node:crypto';

export function verifyRoomToken(token, secret) {
    if (!token || typeof token !== 'string' || !token.includes('.')) {
        throw new Error('Missing mediasoup room token');
    }

    const [serialized, signature] = token.split('.', 2);
    const expected = crypto.createHmac('sha256', secret).update(serialized).digest('hex');

    if (signature !== expected) {
        throw new Error('Invalid mediasoup room token signature');
    }

    const payload = JSON.parse(Buffer.from(serialized, 'base64').toString('utf8'));

    if (payload.exp && Number(payload.exp) < Math.floor(Date.now() / 1000)) {
        throw new Error('Expired mediasoup room token');
    }

    return payload;
}
