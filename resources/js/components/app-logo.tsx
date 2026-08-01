import { BrandGlyph } from '@/components/brand-mark';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <BrandGlyph className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-sm leading-tight font-semibold">
                    UNN Results
                </span>
                <span className="truncate text-xs leading-tight text-muted-foreground">
                    Processing &amp; analytics
                </span>
            </div>
        </>
    );
}
