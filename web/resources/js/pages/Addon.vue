<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AddonDownloadButton from '@/components/AddonDownloadButton.vue';
import Heading from '@/components/Heading.vue';
import type { AddonDownload } from '@/types';

type Props = {
    addon: AddonDownload;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Addon', href: '/addon' }],
    },
});

const commands: { command: string; description: string }[] = [
    { command: 'start', description: 'Start a new session' },
    { command: 'end', description: 'Stop recording the current session' },
    { command: 'status', description: 'Show whether a session is active' },
    { command: 'clear', description: 'Drop the points of the current session' },
    { command: 'clear_all', description: 'Drop every stored session' },
    { command: 'log', description: 'Print the last 20 recorded points' },
    {
        command: 'get_sessions',
        description: 'Open the sessions window and export one as JSON',
    },
];

const clients: { name: string; toc: string }[] = [
    { name: 'Vanilla', toc: 'WhereIveBeen_Vanilla.toc' },
    { name: 'The Burning Crusade', toc: 'WhereIveBeen_TBC.toc' },
    { name: 'Wrath of the Lich King', toc: 'WhereIveBeen_Wrath.toc' },
    { name: 'Cataclysm', toc: 'WhereIveBeen_Cata.toc' },
    { name: 'Mists of Pandaria', toc: 'WhereIveBeen_Mists.toc' },
    { name: 'Retail', toc: 'WhereIveBeen_Mainline.toc' },
];
</script>

<template>
    <Head title="Addon" />

    <h1 class="sr-only">Addon</h1>

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-8">
        <div class="wow-panel flex flex-col gap-4 p-6">
            <Heading
                variant="small"
                title="WhereIveBeen addon"
                description="Records your route in game and exports it as JSON"
            />
            <AddonDownloadButton :addon="addon" />
        </div>

        <div class="wow-panel flex flex-col gap-3 p-6">
            <Heading variant="small" title="Installation" />
            <ol
                class="list-decimal space-y-2 pl-5 text-sm text-muted-foreground"
            >
                <li>Download the archive and unpack it.</li>
                <li>
                    Move the folder to
                    <code class="text-gold"
                        >World of
                        Warcraft/&lt;client&gt;/Interface/AddOns/</code
                    >.
                </li>
                <li>
                    Keep the folder named exactly
                    <code class="text-gold">WhereIveBeen</code>. Under any other
                    name the addon loads but records nothing.
                </li>
                <li>
                    Restart the client or type
                    <code class="text-gold">/reload</code>.
                </li>
            </ol>
        </div>

        <div class="wow-panel flex flex-col gap-3 p-6">
            <Heading variant="small" title="Recording and export" />
            <ol
                class="list-decimal space-y-2 pl-5 text-sm text-muted-foreground"
            >
                <li>
                    Recording starts on its own when you log in — no command
                    needed.
                </li>
                <li>
                    Type <code class="text-gold">/wrivbn get_sessions</code> to
                    open the sessions window.
                </li>
                <li>
                    Click a session to open the export window, then press
                    <code class="text-gold">Ctrl+C</code> to copy the JSON.
                </li>
                <li>
                    Paste it on the
                    <Link
                        href="/game-session/imports"
                        class="text-gold underline-offset-4 hover:underline"
                        >Imports</Link
                    >
                    page and submit.
                </li>
            </ol>
            <p class="text-xs text-muted-foreground">
                Sessions are stored in SavedVariables, so log out or
                <code class="text-gold">/reload</code> before exporting a
                session you have just finished.
            </p>
        </div>

        <div class="wow-panel flex flex-col gap-3 p-6">
            <Heading
                variant="small"
                title="Slash commands"
                description="/whereivebeen, /wivebeen and /wrivbn all work"
            />
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="border-b border-border/70 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="py-2 pr-4 font-medium">Command</th>
                            <th class="py-2 font-medium">What it does</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in commands"
                            :key="row.command"
                            class="border-b border-border/40 last:border-0"
                        >
                            <td class="text-gold py-2 pr-4 font-mono text-xs">
                                /wrivbn {{ row.command }}
                            </td>
                            <td class="py-2 text-muted-foreground">
                                {{ row.description }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wow-panel flex flex-col gap-3 p-6">
            <Heading
                variant="small"
                title="Supported clients"
                description="The archive carries a .toc for every flavor, so one download fits all"
            />
            <ul
                class="grid gap-2 text-sm text-muted-foreground sm:grid-cols-2 lg:grid-cols-3"
            >
                <li v-for="client in clients" :key="client.toc">
                    <span class="text-foreground/90">{{ client.name }}</span>
                    <span class="font-mono text-xs"> · {{ client.toc }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
