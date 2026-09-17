import { locale } from '@/lib/i18n';

const TAGS: Record<string, string> = {
    en: 'en-US',
    ru: 'ru-RU',
};

const tag = TAGS[locale] ?? TAGS.en;
const hour12 = locale !== 'ru';

const dateFormatter = new Intl.DateTimeFormat(tag, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const timeFormatter = new Intl.DateTimeFormat(tag, {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12,
});

const shortTimeFormatter = new Intl.DateTimeFormat(tag, {
    hour: '2-digit',
    minute: '2-digit',
    hour12,
});

const longDateFormatter = new Intl.DateTimeFormat(tag, {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const shortDateFormatter = new Intl.DateTimeFormat(tag, {
    day: 'numeric',
    month: 'short',
});

function parse(value: string | number | Date | null | undefined): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDateTime(
    value: string | number | Date | null | undefined,
    fallback = '—',
): string {
    const date = parse(value);

    return date
        ? `${dateFormatter.format(date)} ${timeFormatter.format(date)}`
        : fallback;
}

export function formatDate(
    value: string | number | Date | null | undefined,
    fallback = '—',
): string {
    const date = parse(value);

    return date ? dateFormatter.format(date) : fallback;
}

export function formatTime(
    value: string | number | Date | null | undefined,
    fallback = '—',
): string {
    const date = parse(value);

    return date ? timeFormatter.format(date) : fallback;
}

export function formatShortTime(
    value: string | number | Date | null | undefined,
    fallback = '—',
): string {
    const date = parse(value);

    return date ? shortTimeFormatter.format(date) : fallback;
}

export function formatLongDate(
    value: string | number | Date | null | undefined,
    fallback = '—',
): string {
    const date = parse(value);

    return date ? longDateFormatter.format(date) : fallback;
}

export function formatShortDate(
    value: string | number | Date | null | undefined,
    fallback = '—',
): string {
    const date = parse(value);

    return date ? shortDateFormatter.format(date) : fallback;
}
