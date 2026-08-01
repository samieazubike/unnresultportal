import type { SVGAttributes } from 'react';
import { BrandGlyph } from '@/components/brand-mark';

/**
 * A thin wrapper so the sidebar keeps its existing call site while the mark
 * itself lives in one place — see brand-mark.tsx for how to swap in the
 * official crest.
 */
export default function AppLogoIcon({ className }: SVGAttributes<SVGElement>) {
    return <BrandGlyph className={className} />;
}
