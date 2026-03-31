<style>
    .api-key-secret {
        border: 1px solid #86efac;
        background: #f0fdf4;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 14px;
    }
    .api-key-secret .mono {
        display: block;
        margin-top: 8px;
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid #bbf7d0;
        background: #fff;
        word-break: break-all;
    }
    .ability-grid {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .ability-item {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 8px 10px;
        background: #fff;
    }
    .ability-item .checkbox { margin: 0; }
    .token-row-scopes {
        display: inline-flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .scope-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        border: 1px solid var(--border);
        padding: 2px 8px;
        font-size: 12px;
        background: #fff;
    }
    @media (max-width: 1100px) {
        .ability-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
