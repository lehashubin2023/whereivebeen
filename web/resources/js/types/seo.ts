export type PageMeta = {
    title: string;
    description: string;
    canonical: string;
    image: string;
    locale: string;
    alternates: Record<string, string>;
    noindex: boolean;
    schema: Record<string, unknown> | null;
};

export type FaqItem = {
    question: string;
    answer: string;
};
