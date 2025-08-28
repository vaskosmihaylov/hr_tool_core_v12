{{-- Service Panel Table Optimization Styles --}}
<style>
/* Inline Critical CSS for Immediate Application */
.fi-panel-service .fi-main {
    max-width: 100% !important;
    width: 100% !important;
    padding: 0 !important;
}

.fi-panel-service .fi-main-content {
    max-width: 100% !important;
    width: 100% !important;
    padding: 1rem !important;
}

.fi-panel-service .fi-ta-table table {
    width: 100% !important;
    table-layout: auto !important;
    min-width: 100% !important;
}

.fi-panel-service .fi-ta-table thead th {
    padding: 0.75rem 0.5rem !important;
    font-size: 0.875rem !important;
    white-space: nowrap !important;
    min-width: fit-content !important;
}

.fi-panel-service .fi-ta-table tbody td {
    padding: 0.75rem 0.5rem !important;
    font-size: 0.875rem !important;
    vertical-align: top !important;
    max-width: none !important;
}
</style>

{{-- Load Full CSS File Asynchronously for Non-Critical Styles --}}
<link rel="preload" href="{{ asset('css/service-table-optimization.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="{{ asset('css/service-table-optimization.css') }}"></noscript>

{{-- Add Compact Mode Toggle (Optional) --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add keyboard shortcut for compact mode: Alt + C
    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.code === 'KeyC') {
            e.preventDefault();
            const panel = document.querySelector('.fi-panel-service');
            if (panel) {
                panel.classList.toggle('fi-compact');
                // Save state to localStorage
                const isCompact = panel.classList.contains('fi-compact');
                localStorage.setItem('service-panel-compact', isCompact);
            }
        }
    });
    
    // Restore compact mode state on load
    const isCompact = localStorage.getItem('service-panel-compact') === 'true';
    if (isCompact) {
        const panel = document.querySelector('.fi-panel-service');
        if (panel) {
            panel.classList.add('fi-compact');
        }
    }
});
</script>
