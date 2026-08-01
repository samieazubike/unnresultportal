import { ChevronsUpDown, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

export type Column<T> = {
    key: string;
    header: string;
    /** Falls back to row[key] when absent. */
    cell?: (row: T) => React.ReactNode;
    sortValue?: (row: T) => string | number;
    align?: 'left' | 'center' | 'right';
    className?: string;
};

/**
 * The card-wrapped table from the design: header with search and a primary
 * action, sortable columns, and a footer reading
 * "Showing 1 to N of M records" beside numbered pagination.
 *
 * Sorting, filtering and paging all happen client-side over an array already
 * in props. That is deliberate — every dataset shown through this component
 * is an aggregate the database has already reduced to a handful of rows
 * (README §2 forbids pulling raw result rows into the client to work on).
 */
export function DataTable<T extends Record<string, unknown>>({
    title,
    columns,
    rows,
    searchKeys,
    action,
    pageSize = 11,
    emptyMessage = 'Nothing to show.',
    rowKey,
    rowClassName,
    className,
}: {
    title?: string;
    columns: Column<T>[];
    rows: T[];
    searchKeys?: (keyof T)[];
    action?: React.ReactNode;
    pageSize?: number;
    emptyMessage?: string;
    rowKey: (row: T, index: number) => string | number;
    rowClassName?: (row: T) => string | undefined;
    className?: string;
}) {
    const [query, setQuery] = useState('');
    const [sort, setSort] = useState<{ key: string; desc: boolean } | null>(null);
    const [page, setPage] = useState(1);

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (!term || !searchKeys?.length) {
            return rows;
        }

        return rows.filter((row) =>
            searchKeys.some((key) =>
                String(row[key] ?? '')
                    .toLowerCase()
                    .includes(term),
            ),
        );
    }, [rows, query, searchKeys]);

    const sorted = useMemo(() => {
        if (!sort) {
            return filtered;
        }

        const column = columns.find((c) => c.key === sort.key);
        const value = (row: T) =>
            column?.sortValue?.(row) ?? (row[sort.key] as string | number);

        return [...filtered].sort((a, b) => {
            const [x, y] = [value(a), value(b)];
            const result =
                typeof x === 'number' && typeof y === 'number'
                    ? x - y
                    : String(x).localeCompare(String(y));

            return sort.desc ? -result : result;
        });
    }, [filtered, sort, columns]);

    const pageCount = Math.max(1, Math.ceil(sorted.length / pageSize));
    const current = Math.min(page, pageCount);
    const start = (current - 1) * pageSize;
    const visible = sorted.slice(start, start + pageSize);

    const toggleSort = (key: string) =>
        setSort((existing) =>
            existing?.key === key
                ? existing.desc
                    ? null
                    : { key, desc: true }
                : { key, desc: false },
        );

    return (
        <section
            className={cn(
                'flex flex-col overflow-hidden rounded-[var(--radius)] border border-border bg-card shadow-xs',
                className,
            )}
        >
            {(title || action || searchKeys?.length) && (
                <header className="flex flex-wrap items-center gap-3 border-b border-border p-4">
                    {title ? (
                        <h2 className="text-sm font-semibold">{title}</h2>
                    ) : null}

                    <div className="ml-auto flex items-center gap-2">
                        {searchKeys?.length ? (
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={query}
                                    onChange={(event) => {
                                        setQuery(event.target.value);
                                        setPage(1);
                                    }}
                                    placeholder="Search"
                                    className="h-8 w-44 pl-8 text-sm"
                                    aria-label={`Search ${title ?? 'table'}`}
                                />
                            </div>
                        ) : null}
                        {action}
                    </div>
                </header>
            )}

            <div className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            {columns.map((column) => (
                                <TableHead
                                    key={column.key}
                                    className={cn(
                                        'whitespace-nowrap text-xs font-medium text-muted-foreground',
                                        column.align === 'right' && 'text-right',
                                        column.align === 'center' && 'text-center',
                                        column.className,
                                    )}
                                >
                                    {column.sortValue || column.key in (rows[0] ?? {}) ? (
                                        <button
                                            type="button"
                                            onClick={() => toggleSort(column.key)}
                                            className="inline-flex items-center gap-1 hover:text-foreground"
                                        >
                                            {column.header}
                                            <ChevronsUpDown className="size-3 opacity-50" />
                                        </button>
                                    ) : (
                                        column.header
                                    )}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        {visible.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-28 text-center text-sm text-muted-foreground"
                                >
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        ) : (
                            visible.map((row, index) => (
                                <TableRow
                                    key={rowKey(row, start + index)}
                                    className={rowClassName?.(row)}
                                >
                                    {columns.map((column) => (
                                        <TableCell
                                            key={column.key}
                                            className={cn(
                                                'text-sm',
                                                column.align === 'right' && 'text-right',
                                                column.align === 'center' && 'text-center',
                                                column.className,
                                            )}
                                        >
                                            {column.cell
                                                ? column.cell(row)
                                                : String(row[column.key] ?? '')}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            {sorted.length > 0 ? (
                <footer className="flex flex-wrap items-center gap-3 border-t border-border px-4 py-3">
                    <p className="text-xs text-muted-foreground">
                        Showing {start + 1} to{' '}
                        {Math.min(start + pageSize, sorted.length)} of{' '}
                        {sorted.length} records
                    </p>

                    {pageCount > 1 ? (
                        <nav className="ml-auto flex items-center gap-1" aria-label="Pagination">
                            <PageButton
                                disabled={current === 1}
                                onClick={() => setPage(current - 1)}
                            >
                                ‹
                            </PageButton>

                            {pageNumbers(current, pageCount).map((entry, index) =>
                                entry === null ? (
                                    <span
                                        key={`gap-${index}`}
                                        className="px-1 text-xs text-muted-foreground"
                                    >
                                        …
                                    </span>
                                ) : (
                                    <PageButton
                                        key={entry}
                                        active={entry === current}
                                        onClick={() => setPage(entry)}
                                    >
                                        {entry}
                                    </PageButton>
                                ),
                            )}

                            <PageButton
                                disabled={current === pageCount}
                                onClick={() => setPage(current + 1)}
                            >
                                ›
                            </PageButton>
                        </nav>
                    ) : null}
                </footer>
            ) : null}
        </section>
    );
}

function PageButton({
    children,
    active,
    disabled,
    onClick,
}: {
    children: React.ReactNode;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'inline-flex size-7 items-center justify-center rounded-md text-xs transition-colors',
                active
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                disabled && 'pointer-events-none opacity-40',
            )}
        >
            {children}
        </button>
    );
}

/** First, last, and a window around the current page, with gaps elided. */
function pageNumbers(current: number, total: number): (number | null)[] {
    if (total <= 7) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }

    const pages = new Set([1, total, current, current - 1, current + 1]);
    const sorted = [...pages].filter((page) => page >= 1 && page <= total).sort((a, b) => a - b);

    return sorted.flatMap((page, index) =>
        index > 0 && page - sorted[index - 1] > 1 ? [null, page] : [page],
    );
}
