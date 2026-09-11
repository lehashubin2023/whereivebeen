export type CryptoWallet = {
    label: string;
    address: string;
};

export type SupportChannels = {
    boosty: string | null;
    telegram: string | null;
    crypto: CryptoWallet[];
};
