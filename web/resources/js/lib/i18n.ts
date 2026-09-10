type Dictionary = Record<string, string>;

declare global {
    interface Window {
        __locale?: string;
        __translations?: Dictionary;
    }
}

const dictionary: Dictionary =
    typeof window === 'undefined' ? {} : (window.__translations ?? {});

export const locale: string =
    typeof window === 'undefined' ? 'en' : (window.__locale ?? 'en');

export function t(
    key: string,
    replacements: Record<string, string | number> = {},
): string {
    let line = dictionary[key] ?? key;

    for (const [placeholder, value] of Object.entries(replacements)) {
        line = line.replaceAll(`:${placeholder}`, String(value));
    }

    return line;
}
