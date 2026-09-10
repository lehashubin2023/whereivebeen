<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AddonDownloadButton from '@/components/AddonDownloadButton.vue';
import Heading from '@/components/Heading.vue';
import { t } from '@/lib/i18n';
import type { AddonDownload } from '@/types';

type Props = {
    addon: AddonDownload;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Addon'), href: '/addon' }],
    },
});

const commands: { command: string; description: string }[] = [
    { command: 'start', description: t('Start a new session') },
    { command: 'end', description: t('Stop recording the current session') },
    { command: 'status', description: t('Show whether a session is active') },
    {
        command: 'clear',
        description: t('Drop the points of the current session'),
    },
    { command: 'clear_all', description: t('Drop every stored session') },
    { command: 'log', description: t('Print the last 20 recorded points') },
    {
        command: 'get_sessions',
        description: t('Open the sessions window and export one'),
    },
    { command: 'delete <id>', description: t('Delete one session by id') },
    {
        command: 'prune <points>',
        description: t('Delete stored sessions with fewer points'),
    },
    {
        command: 'raw',
        description: t('Export raw JSON instead of the compressed line'),
    },
];

const clients: { name: string; toc: string }[] = [
    { name: t('Vanilla'), toc: 'WhereIveBeen_Vanilla.toc' },
    { name: t('The Burning Crusade'), toc: 'WhereIveBeen_TBC.toc' },
    { name: t('Wrath of the Lich King'), toc: 'WhereIveBeen_Wrath.toc' },
    { name: t('Cataclysm'), toc: 'WhereIveBeen_Cata.toc' },
    { name: t('Mists of Pandaria'), toc: 'WhereIveBeen_Mists.toc' },
    { name: t('Retail'), toc: 'WhereIveBeen_Mainline.toc' },
];
</script>

<template>
    <Head :title="t('Addon')" />

    <h1 class="sr-only">{{ t('Addon') }}</h1>

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-8">
        <div class="wow-panel flex flex-col gap-4 p-6">
            <Heading
                variant="small"
                :title="t('WhereIveBeen addon')"
                :description="
                    t(
                        'Records your route in game and exports it as one compact line',
                    )
                "
            />
            <AddonDownloadButton :addon="addon" />
        </div>

        <div class="wow-panel flex flex-col gap-3 p-6">
            <Heading variant="small" :title="t('Installation')" />
            <ol
                class="list-decimal space-y-2 pl-5 text-sm text-muted-foreground"
            >
                <li>{{ t('Download the archive and unpack it.') }}</li>
                <li>
                    {{ t('Move the folder to') }}
                    <code class="text-gold"
                        >World of
                        Warcraft/&lt;client&gt;/Interface/AddOns/</code
                    >.
                </li>
                <li>
                    {{ t('Keep the folder named exactly') }}
                    <code class="text-gold">WhereIveBeen</code>.
                    {{
                        t(
                            'Under any other name the addon loads but records nothing.',
                        )
                    }}
                </li>
                <li>
                    {{ t('Restart the client or type') }}
                    <code class="text-gold">/reload</code>.
                </li>
            </ol>
        </div>

        <div class="wow-panel flex flex-col gap-3 p-6">
            <Heading variant="small" :title="t('Recording and export')" />
            <ol
                class="list-decimal space-y-2 pl-5 text-sm text-muted-foreground"
            >
                <li>
                    {{
                        t(
                            'Recording starts on its own when you log in — no command needed.',
                        )
                    }}
                </li>
                <li>
                    {{ t('Type') }}
                    <code class="text-gold">/wivbn get_sessions</code>
                    {{ t('to open the sessions window.') }}
                </li>
                <li>
                    {{
                        t(
                            'Click a session to open the export window, then press',
                        )
                    }}
                    <code class="text-gold">Ctrl+C</code>
                    {{ t('to copy the exported line.') }}
                </li>
                <li>
                    {{ t('Paste it on the') }}
                    <Link
                        href="/game-session/imports"
                        class="text-gold underline-offset-4 hover:underline"
                        >{{ t('Imports') }}</Link
                    >
                    {{ t('page and submit.') }}
                </li>
            </ol>
            <p class="text-xs text-muted-foreground">
                {{ t('Sessions are stored in SavedVariables, so log out or') }}
                <code class="text-gold">/reload</code>
                {{ t('before exporting a session you have just finished.') }}
            </p>
        </div>

        <div class="wow-panel flex flex-col gap-3 p-6">
            <Heading
                variant="small"
                :title="t('Slash commands')"
                :description="
                    t('/whereivebeen, /wivebeen, /wivbn and /wivb all work')
                "
            />
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="border-b border-border/70 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="py-2 pr-4 font-medium">
                                {{ t('Command') }}
                            </th>
                            <th class="py-2 font-medium">
                                {{ t('What it does') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in commands"
                            :key="row.command"
                            class="border-b border-border/40 last:border-0"
                        >
                            <td class="text-gold py-2 pr-4 font-mono text-xs">
                                /wivbn {{ row.command }}
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
                :title="t('Supported clients')"
                :description="
                    t(
                        'The archive carries a .toc for every flavor, so one download fits all',
                    )
                "
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
