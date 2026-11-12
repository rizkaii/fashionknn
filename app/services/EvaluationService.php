<?php
class EvaluationService {
    /**
     * Calculate all metrics from predictions array.
     * $predictions = [['actual'=>str, 'predicted'=>str], ...]
     * Returns metrics array with confusion matrix, per-class metrics, macro averages.
     */
    public function calculate(array $predictions): array {
        $classes = ['tren', 'kurang tren', 'tidak tren'];

        // Build confusion matrix: $cm[$actual][$predicted] = count
        $cm = [];
        foreach ($classes as $c1) {
            foreach ($classes as $c2) {
                $cm[$c1][$c2] = 0;
            }
        }

        $total   = 0;
        $correct = 0;

        foreach ($predictions as $p) {
            $actual    = strtolower(trim($p['actual']    ?? ''));
            $predicted = strtolower(trim($p['predicted'] ?? ''));
            if (!in_array($actual, $classes) || !in_array($predicted, $classes)) {
                continue;
            }
            $cm[$actual][$predicted]++;
            $total++;
            if ($actual === $predicted) {
                $correct++;
            }
        }

        $accuracy = $total > 0 ? ($correct / $total) : 0.0;

        // Per-class metrics
        $per_class = [];
        $macro_precision = 0.0;
        $macro_recall    = 0.0;
        $macro_f1        = 0.0;

        foreach ($classes as $cls) {
            $tp = $cm[$cls][$cls];

            // FP: predicted as $cls but actually something else
            $fp = 0;
            foreach ($classes as $other) {
                if ($other !== $cls) {
                    $fp += $cm[$other][$cls];
                }
            }

            // FN: actually $cls but predicted as something else
            $fn = 0;
            foreach ($classes as $other) {
                if ($other !== $cls) {
                    $fn += $cm[$cls][$other];
                }
            }

            $support   = $tp + $fn;
            $precision = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : 0.0;
            $recall    = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 0.0;
            $f1        = ($precision + $recall) > 0
                ? 2 * $precision * $recall / ($precision + $recall)
                : 0.0;

            $per_class[$cls] = [
                'precision' => round($precision, 6),
                'recall'    => round($recall, 6),
                'f1'        => round($f1, 6),
                'support'   => $support,
                'tp'        => $tp,
                'fp'        => $fp,
                'fn'        => $fn,
            ];

            $macro_precision += $precision;
            $macro_recall    += $recall;
            $macro_f1        += $f1;
        }

        $n = count($classes);
        $macro = [
            'precision' => round($macro_precision / $n, 6),
            'recall'    => round($macro_recall    / $n, 6),
            'f1'        => round($macro_f1        / $n, 6),
        ];

        return [
            'confusion_matrix' => $cm,
            'accuracy'         => round($accuracy, 6),
            'per_class'        => $per_class,
            'macro'            => $macro,
            'total'            => $total,
            'correct'          => $correct,
        ];
    }
}
