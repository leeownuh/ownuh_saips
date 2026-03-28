<script src="assets/js/sidebar.js"></script>
<script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/libs/simplebar/simplebar.min.js"></script>
<script src="assets/libs/sweetalert2/sweetalert2.min.js"></script>
<script src="assets/js/pages/scroll-top.init.js"></script>
<script src="assets/js/app.js" type="module"></script>
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"],[title]').forEach(el => {
    try { new bootstrap.Tooltip(el, { trigger: 'hover' }); } catch (e) {}
});
</script>
