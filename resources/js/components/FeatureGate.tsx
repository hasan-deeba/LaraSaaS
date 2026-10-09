import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { larasaasFetch } from '../larasaas';

/**
 * Feature flags (Laravel Pennant), evaluated server-side and consumed
 * through a tiny provider:
 *
 *   <LaraSaaSFeaturesProvider>
 *       <FeatureGate flag="ai-assistant" fallback={<UpgradeCard />}>
 *           <AssistantPanel />
 *       </FeatureGate>
 *   </LaraSaaSFeaturesProvider>
 */

interface FlagsState {
    flags: Record<string, boolean>;
    loading: boolean;
    refresh: () => void;
}

const FlagsContext = createContext<FlagsState>({ flags: {}, loading: true, refresh: () => {} });

export function LaraSaaSFeaturesProvider({ children, initial }: { children: React.ReactNode; initial?: Record<string, boolean> }) {
    const [flags, setFlags] = useState<Record<string, boolean>>(initial ?? {});
    const [loading, setLoading] = useState(!initial);
    const [tick, setTick] = useState(0);

    useEffect(() => {
        let alive = true;

        larasaasFetch('features')
            .then(({ data }) => alive && setFlags(data ?? {}))
            .catch(() => alive && setFlags((current) => current))
            .finally(() => alive && setLoading(false));

        return () => {
            alive = false;
        };
    }, [tick]);

    const value = useMemo(() => ({ flags, loading, refresh: () => setTick((n) => n + 1) }), [flags, loading]);

    return <FlagsContext.Provider value={value}>{children}</FlagsContext.Provider>;
}

export function useFeatures(): FlagsState {
    return useContext(FlagsContext);
}

export function useFeature(flag: string): boolean {
    const { flags } = useContext(FlagsContext);
    return Boolean(flags[flag]);
}

interface GateProps {
    flag: string;
    fallback?: React.ReactNode;
    children: React.ReactNode;
}

export function FeatureGate({ flag, fallback = null, children }: GateProps) {
    const { flags, loading } = useContext(FlagsContext);

    if (loading && !Object.keys(flags).length) return null;

    return <>{flags[flag] ? children : fallback}</>;
}

export default FeatureGate;
