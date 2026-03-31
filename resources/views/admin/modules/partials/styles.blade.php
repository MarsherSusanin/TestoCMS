<style>
    .module-card { margin-top: 10px; }
    .module-card:first-of-type { margin-top: 0; }
    .module-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        list-style: none;
    }
    .module-summary::-webkit-details-marker { display: none; }
    .module-meta {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        color: var(--muted);
        font-size: 13px;
    }
    .module-error {
        background: var(--error-bg);
        border: 1px solid var(--error-border);
        border-radius: 10px;
        padding: 10px 12px;
        margin-top: 10px;
        color: #991b1b;
    }
    .module-info-table td { padding: 8px 6px; }
    .module-action-stack { display: grid; gap: 10px; }
    .module-action-stack form { margin: 0; }
    .module-action-row { display: flex; gap: 8px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; }
    .module-path {
        display: block;
        margin-top: 4px;
        font-size: 12px;
        color: var(--muted);
        word-break: break-all;
    }
    @media (max-width: 1024px) {
        .module-summary { align-items: flex-start; }
    }
</style>
