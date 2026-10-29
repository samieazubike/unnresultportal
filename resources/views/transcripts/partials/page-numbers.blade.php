<script type="text/php">
    if (isset($pdf)) {
        $pdf->page_script('
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $pdf->text(516, 812, $PAGE_NUM . " of " . $PAGE_COUNT, $font, 8, [0.4, 0.4, 0.4]);
        ');
    }
</script>
