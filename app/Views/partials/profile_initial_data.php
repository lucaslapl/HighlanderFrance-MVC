<?php
/**
 * Données initiales injectées pour profil.js (camembert, barres, calendrier).
 * Variables attendues : $stats, $activityData.
 */
?>
<script>
window.__initialClassesKilled = <?= json_encode($stats['classes_killed']) ?>;
window.__initialTopMaps = <?= json_encode($stats['top_maps']) ?>;
window.__activityData = <?= json_encode($activityData) ?>;
</script>
