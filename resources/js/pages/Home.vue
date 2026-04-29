<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

type ModuleCard = {
    name: string;
    description: string;
    status: string;
};

type Plan = {
    id: number;
    code: string;
    name: string;
    tier: string;
    price_monthly_cents: number;
    credits_included: number;
    max_storage_gb: number;
    max_destinations: number;
    max_guests: number;
    max_stream_hours: number;
    features: string[];
};

type StudioConfig = {
    engine: {
        driver: string;
        output_mode: string;
        base_url: string | null;
    };
    ffmpeg: {
        bin: string;
        ffprobe_bin: string;
    };
    mediasoup: {
        enabled: boolean;
        signaling_url: string;
        listen_host: string;
        listen_port: number;
        rtc_listen_ip: string;
        rtc_announced_address: string | null;
        rtc_min_port: number;
        rtc_max_port: number;
        log_level: string;
    };
    turn: {
        urls: string[];
        username: string | null;
    };
    destinations: {
        providers: string[];
    };
    assets: {
        import_sources: string[];
        playlist_enabled: boolean;
        scene_templates_enabled: boolean;
    };
};

const props = defineProps<{
    modules: ModuleCard[];
    plans: { data: Plan[] };
    studioConfig: StudioConfig;
    checklist: string[];
}>();

const monthlyPlans = computed(() =>
    props.plans.data.map((plan) => ({
        ...plan,
        price: `$${(plan.price_monthly_cents / 100).toFixed(0)}/mo`,
    })),
);

const runtimeBadges = computed(() => [
    `FFmpeg: ${props.studioConfig.engine.driver}`,
    `Mediasoup: ${props.studioConfig.mediasoup.enabled ? 'enabled' : 'disabled'}`,
    `Output: ${props.studioConfig.engine.output_mode}`,
]);
</script>

<template>
    <Head title="Studio OS" />

    <div class="min-h-screen bg-[var(--color-ink)] text-[var(--color-paper)]">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="hero-orb top-[-8rem] left-[-12rem]" />
            <div
                class="hero-orb hero-orb-secondary top-[8rem] right-[-10rem]"
            />
            <div class="hero-grid" />
        </div>

        <main
            class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 pt-8 pb-12 lg:px-10"
        >
            <header
                class="mb-10 flex flex-col gap-6 border-b border-white/10 pb-8 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="max-w-3xl">
                    <p class="studio-kicker">LiveReacting OS</p>
                    <h1
                        class="mt-3 max-w-4xl text-5xl font-semibold tracking-[-0.04em] text-white lg:text-7xl"
                    >
                        A full live production stack with mediasoup guest rooms,
                        FFmpeg egress, and a studio-grade control plane.
                    </h1>
                    <p
                        class="mt-5 max-w-2xl text-base text-white/70 lg:text-lg"
                    >
                        The starter shell has been replaced with a real studio
                        surface. Core modules, guest signaling, billing,
                        interactive tools, playlists, and templates are now
                        wired into the app.
                    </p>
                </div>

                <div class="grid gap-3 lg:min-w-[20rem]">
                    <div class="glass-panel p-4">
                        <p class="panel-label">Runtime</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="badge in runtimeBadges"
                                :key="badge"
                                class="rounded-full border border-white/15 bg-white/8 px-3 py-1 text-xs tracking-[0.18em] text-white/75 uppercase"
                            >
                                {{ badge }}
                            </span>
                        </div>
                    </div>
                    <div class="glass-panel p-4">
                        <p class="panel-label">Signaling</p>
                        <p class="mt-3 text-sm text-white/75">
                            {{ studioConfig.mediasoup.signaling_url }}
                        </p>
                        <p class="mt-2 text-xs text-white/45">
                            Ports {{ studioConfig.mediasoup.rtc_min_port }}-{{
                                studioConfig.mediasoup.rtc_max_port
                            }}
                        </p>
                    </div>
                </div>
            </header>

            <section class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                <div class="glass-panel p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="panel-label">Command Deck</p>
                            <h2 class="mt-2 text-2xl font-semibold text-white">
                                Studio runtime map
                            </h2>
                        </div>
                        <div
                            class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs tracking-[0.2em] text-emerald-200 uppercase"
                        >
                            Online
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <div class="panel-stack">
                            <span class="stack-index">01</span>
                            <h3>Guest ingress</h3>
                            <p>
                                Laravel issues invite and host tokens, mediasoup
                                handles peer transports, and TURN settings are
                                delivered with the signaling payload.
                            </p>
                        </div>
                        <div class="panel-stack">
                            <span class="stack-index">02</span>
                            <h3>Scene composition</h3>
                            <p>
                                Projects carry active scenes, ordered layers,
                                reusable templates, and sync-ready studio
                                payload snapshots.
                            </p>
                        </div>
                        <div class="panel-stack">
                            <span class="stack-index">03</span>
                            <h3>Interactive layer</h3>
                            <p>
                                Polls, trivia, featured comments, countdowns,
                                and overlay-ready results now sit in the same
                                project domain model.
                            </p>
                        </div>
                        <div class="panel-stack">
                            <span class="stack-index">04</span>
                            <h3>Billing control</h3>
                            <p>
                                Plans, credit wallets, event history, and
                                runtime limit checks guard video imports,
                                guests, scenes, and stream duration.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="glass-panel p-6">
                    <p class="panel-label">Verification</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">
                        Build checklist
                    </h2>
                    <ul class="mt-5 space-y-3">
                        <li
                            v-for="item in checklist"
                            :key="item"
                            class="flex items-start gap-3 rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm text-white/72"
                        >
                            <span
                                class="mt-1 h-2.5 w-2.5 rounded-full bg-[var(--color-signal)]"
                            />
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="mt-6 grid gap-5 lg:grid-cols-[1.25fr_1fr]">
                <div class="glass-panel p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="panel-label">Platform Modules</p>
                            <h2 class="mt-2 text-2xl font-semibold text-white">
                                Everything shipped in the same studio surface
                            </h2>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <article
                            v-for="module in modules"
                            :key="module.name"
                            class="rounded-[1.5rem] border border-white/10 bg-[linear-gradient(180deg,rgba(255,255,255,0.09),rgba(255,255,255,0.03))] p-5"
                        >
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-white">
                                    {{ module.name }}
                                </h3>
                                <span
                                    class="rounded-full bg-white/10 px-3 py-1 text-[11px] tracking-[0.2em] text-white/65 uppercase"
                                >
                                    {{ module.status }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-white/65">
                                {{ module.description }}
                            </p>
                        </article>
                    </div>
                </div>

                <div class="glass-panel p-6">
                    <p class="panel-label">Runtime Details</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">
                        Studio transport profile
                    </h2>

                    <div class="mt-5 space-y-4 text-sm text-white/70">
                        <div
                            class="rounded-2xl border border-white/8 bg-black/20 p-4"
                        >
                            <p class="font-medium text-white">
                                FFmpeg binaries
                            </p>
                            <p class="mt-2 font-mono text-xs text-white/55">
                                {{ studioConfig.ffmpeg.bin }}
                            </p>
                            <p class="mt-1 font-mono text-xs text-white/55">
                                {{ studioConfig.ffmpeg.ffprobe_bin }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-white/8 bg-black/20 p-4"
                        >
                            <p class="font-medium text-white">Import sources</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="source in studioConfig.assets
                                        .import_sources"
                                    :key="source"
                                    class="rounded-full border border-white/10 bg-white/6 px-3 py-1 text-xs tracking-[0.16em] uppercase"
                                >
                                    {{ source }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-white/8 bg-black/20 p-4"
                        >
                            <p class="font-medium text-white">Destinations</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="provider in studioConfig.destinations
                                        .providers"
                                    :key="provider"
                                    class="rounded-full border border-white/10 bg-white/6 px-3 py-1 text-xs tracking-[0.16em] uppercase"
                                >
                                    {{ provider }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="glass-panel mt-6 p-6">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div>
                        <p class="panel-label">Plans and Limits</p>
                        <h2 class="mt-2 text-2xl font-semibold text-white">
                            Subscription profiles now enforce runtime behavior
                        </h2>
                    </div>
                    <p class="max-w-xl text-sm text-white/60">
                        Imports, guest capacity, interactive count,
                        destinations, scenes, and stream duration are checked
                        against the active plan before the studio accepts the
                        action.
                    </p>
                </div>

                <div class="mt-6 grid gap-4 xl:grid-cols-3">
                    <article
                        v-for="plan in monthlyPlans"
                        :key="plan.id"
                        class="rounded-[1.75rem] border border-white/10 bg-[linear-gradient(160deg,rgba(241,90,41,0.16),rgba(255,255,255,0.03))] p-5"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p
                                    class="text-xs tracking-[0.22em] text-white/50 uppercase"
                                >
                                    {{ plan.tier }}
                                </p>
                                <h3
                                    class="mt-2 text-2xl font-semibold text-white"
                                >
                                    {{ plan.name }}
                                </h3>
                            </div>
                            <p
                                class="text-lg font-medium text-[var(--color-signal)]"
                            >
                                {{ plan.price }}
                            </p>
                        </div>

                        <div
                            class="mt-5 grid grid-cols-2 gap-3 text-sm text-white/72"
                        >
                            <div
                                class="rounded-2xl border border-white/8 bg-black/20 p-3"
                            >
                                <p
                                    class="text-xs tracking-[0.18em] text-white/45 uppercase"
                                >
                                    Credits
                                </p>
                                <p
                                    class="mt-2 text-lg font-semibold text-white"
                                >
                                    {{ plan.credits_included }}
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-white/8 bg-black/20 p-3"
                            >
                                <p
                                    class="text-xs tracking-[0.18em] text-white/45 uppercase"
                                >
                                    Storage
                                </p>
                                <p
                                    class="mt-2 text-lg font-semibold text-white"
                                >
                                    {{ plan.max_storage_gb }} GB
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-white/8 bg-black/20 p-3"
                            >
                                <p
                                    class="text-xs tracking-[0.18em] text-white/45 uppercase"
                                >
                                    Destinations
                                </p>
                                <p
                                    class="mt-2 text-lg font-semibold text-white"
                                >
                                    {{ plan.max_destinations }}
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-white/8 bg-black/20 p-3"
                            >
                                <p
                                    class="text-xs tracking-[0.18em] text-white/45 uppercase"
                                >
                                    Guests
                                </p>
                                <p
                                    class="mt-2 text-lg font-semibold text-white"
                                >
                                    {{ plan.max_guests }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <span
                                v-for="feature in plan.features"
                                :key="feature"
                                class="rounded-full border border-white/10 bg-white/6 px-3 py-1 text-xs text-white/62"
                            >
                                {{ feature }}
                            </span>
                        </div>
                    </article>
                </div>
            </section>
        </main>
    </div>
</template>
