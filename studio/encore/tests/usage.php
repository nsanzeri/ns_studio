<?php
// Verify the real logger with rollback-only records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../_private/_core/bootstrap.php';
require __DIR__ . '/../../_private/_core/tool_usage.php';
if (!tool_usage_table_exists($pdo)) throw new RuntimeException('tool_usage_log is missing.');
$pdo->beginTransaction();
try {
    foreach (['encore_contacts','encore_followups','encore_interactions','encore_history'] as $feature) {
        if (!log_tool_usage($pdo, ['feature_key'=>$feature,'action_key'=>'instrumentation_test','status'=>'success'])) {
            throw new RuntimeException('Logger failed for '.$feature);
        }
        $query = $pdo->prepare("SELECT COUNT(*) FROM tool_usage_log WHERE feature_key=? AND action_key='instrumentation_test' AND status='success'");
        $query->execute([$feature]);
        if ((int)$query->fetchColumn() < 1) throw new RuntimeException('Event unavailable to admin query.');
    }
    echo "PASS: All four Encore feature groups write to the admin usage table. Test events rolled back.\n";
} finally {
    $pdo->rollBack();
}
