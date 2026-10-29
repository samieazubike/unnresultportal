<style>
    @page { margin: 28mm 18mm 22mm 18mm; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10px;
        color: #1a1a1a;
        line-height: 1.45;
    }

    .crest {
        text-align: center;
        border-bottom: 2px solid #1a1a1a;
        padding-bottom: 10px;
        margin-bottom: 16px;
    }
    .crest h1 { font-size: 15px; margin: 0 0 2px; letter-spacing: 0.5px; }
    .crest h2 { font-size: 11px; margin: 0; font-weight: normal; color: #444; }
    .crest .doc-title {
        margin-top: 8px;
        font-size: 12px;
        font-weight: bold;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    table { width: 100%; border-collapse: collapse; }

    .particulars { margin-bottom: 18px; }
    .particulars td { padding: 3px 0; vertical-align: top; }
    .particulars .label { width: 110px; color: #555; }
    .particulars .value { font-weight: bold; }

    .session-title {
        margin: 14px 0 5px;
        padding: 5px 8px;
        background: #f0f2f5;
        font-weight: bold;
        font-size: 10px;
    }

    .results th {
        text-align: left;
        border-bottom: 1px solid #999;
        padding: 5px 6px;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #444;
    }
    .results td { padding: 4px 6px; border-bottom: 1px solid #eee; }
    .results .num { text-align: center; }
    .results tfoot td {
        border-top: 1px solid #999;
        border-bottom: none;
        font-weight: bold;
        padding-top: 6px;
    }
    .fail { color: #b00020; font-weight: bold; }

    .summary {
        margin-top: 22px;
        border: 1px solid #999;
        padding: 10px 12px;
    }
    .summary td { padding: 4px 0; }
    .summary .label { color: #555; width: 150px; }
    .summary .value { font-weight: bold; }
    .summary .headline { font-size: 13px; }

    .scale { margin-top: 18px; font-size: 8.5px; color: #555; }
    .scale th, .scale td { border: 1px solid #ddd; padding: 3px 5px; text-align: center; }

    .footer {
        position: fixed;
        bottom: -14mm;
        left: 0;
        right: 0;
        font-size: 8px;
        color: #666;
        border-top: 1px solid #ddd;
        padding-top: 4px;
    }
    .footer .right { float: right; }

    .empty { padding: 20px; text-align: center; color: #666; font-style: italic; }
</style>
