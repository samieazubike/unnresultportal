import * as React from 'react';

const MOBILE_BREAKPOINT = 768;

const isNarrow = () =>
    typeof window !== 'undefined' && window.innerWidth < MOBILE_BREAKPOINT;

export function useIsMobile() {
    // Read the width in the initialiser rather than in the effect: setting
    // state synchronously from an effect body costs a second render pass on
    // every mount, and is what react-hooks/set-state-in-effect flags.
    const [isMobile, setIsMobile] = React.useState<boolean>(isNarrow);

    React.useEffect(() => {
        const mql = window.matchMedia(
            `(max-width: ${MOBILE_BREAKPOINT - 1}px)`,
        );
        const onChange = () => setIsMobile(isNarrow());

        mql.addEventListener('change', onChange);

        return () => mql.removeEventListener('change', onChange);
    }, []);

    return isMobile;
}
