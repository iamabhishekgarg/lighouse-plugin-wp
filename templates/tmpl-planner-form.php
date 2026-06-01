<?php
// Redirects to dashboard — the form modal lives there.
// This redirect is handled in class-lh-auth.php before output if needed.
// If we reach here as planner, send them to dashboard via JS.
?>
<script>window.location.href = '<?php echo esc_js( home_url("/planner-dashboard") ); ?>';</script>
