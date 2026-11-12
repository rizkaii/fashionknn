<?php
require_once __DIR__ . '/PreprocessingService.php';
require_once __DIR__ . '/EvaluationService.php';

class KNNService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Calculate Euclidean distance between two normalized feature vectors
     * [sold_n, rating_n, price_n].
     */
    public function euclideanDistance(array $a, array $b): float {
        return sqrt(
            ($a[0] - $b[0]) ** 2 +
            ($a[1] - $b[1]) ** 2 +
            ($a[2] - $b[2]) ** 2
        );
    }

    /**
     * Classify a single point against training data.
     *
     * $point_normalized   = ['sold_n'=>, 'rating_n'=>, 'price_n'=>] (or indexed [sold_n,rating_n,price_n])
     * $train_normalized   = array of training rows with sold_n, rating_n, price_n, label
     * $k                  = number of neighbors
     *
     * Returns:
     * [
     *   'prediction' => str,
     *   'neighbors'  => [['product'=>row, 'distance'=>float, 'label'=>str], ...],
     *   'votes'      => ['tren'=>n, 'kurang tren'=>n, 'tidak tren'=>n],
     * ]
     */
    public function classify(array $point_normalized, array $train_normalized, int $k): array {
        // Resolve feature vector from point
        if (isset($point_normalized['sold_n'])) {
            $pv = [
                (float)$point_normalized['sold_n'],
                (float)$point_normalized['rating_n'],
                (float)$point_normalized['price_n'],
            ];
        } else {
            $pv = [
                (float)($point_normalized[0] ?? 0),
                (float)($point_normalized[1] ?? 0),
                (float)($point_normalized[2] ?? 0),
            ];
        }

        // Calculate distances
        $distances = [];
        foreach ($train_normalized as $train_row) {
            $tv = [
                (float)$train_row['sold_n'],
                (float)$train_row['rating_n'],
                (float)$train_row['price_n'],
            ];
            $distances[] = [
                'distance' => $this->euclideanDistance($pv, $tv),
                'label'    => $train_row['label'],
                'product'  => $train_row,
            ];
        }

        // Sort ascending by distance
        usort($distances, fn($a, $b) => $a['distance'] <=> $b['distance']);

        // Take top-k
        $k = min($k, count($distances));
        $neighbors = array_slice($distances, 0, $k);

        // Vote count
        $votes = ['tren' => 0, 'kurang tren' => 0, 'tidak tren' => 0];
        $label_distances = ['tren' => [], 'kurang tren' => [], 'tidak tren' => []];

        foreach ($neighbors as $n) {
            $lbl = strtolower(trim($n['label']));
            if (isset($votes[$lbl])) {
                $votes[$lbl]++;
                $label_distances[$lbl][] = $n['distance'];
            }
        }

        // Find max votes
        $max_votes = max($votes);

        // Collect tied labels
        $tied = array_keys(array_filter($votes, fn($v) => $v === $max_votes));

        if (count($tied) === 1) {
            $prediction = $tied[0];
        } else {
            // Tie-breaking: pick label with smallest average distance
            $best_label = null;
            $best_avg   = PHP_FLOAT_MAX;
            foreach ($tied as $lbl) {
                $dists = $label_distances[$lbl];
                $avg   = count($dists) > 0 ? array_sum($dists) / count($dists) : PHP_FLOAT_MAX;
                if ($avg < $best_avg) {
                    $best_avg   = $avg;
                    $best_label = $lbl;
                }
            }
            $prediction = $best_label;
        }

        $neighbors_out = [];
        foreach ($neighbors as $n) {
            $neighbors_out[] = [
                'product'  => $n['product'],
                'distance' => $n['distance'],
                'label'    => $n['label'],
            ];
        }

        return [
            'prediction' => $prediction,
            'neighbors'  => $neighbors_out,
            'votes'      => $votes,
        ];
    }

    /**
     * Run full KNN process: train, evaluate on test, predict unlabeled, save to DB.
     * Returns array with all metrics and results.
     */
    public function runProcess(int $k, int $split_ratio, int $seed): array {
        $pre_svc  = new PreprocessingService($this->pdo);
        $eval_svc = new EvaluationService();

        // 1. Get labeled data, check minimum
        $labeled = $pre_svc->getLabeledData();
        if (count($labeled) < 3) {
            throw new \Exception(
                'Data berlabel tidak cukup. Minimal 3 produk berlabel diperlukan untuk menjalankan proses KNN. ' .
                'Saat ini tersedia: ' . count($labeled) . ' data.'
            );
        }

        // 2. Calculate norm params from ALL labeled data
        $norm_params = $pre_svc->calcNormParams($labeled);

        // 3. Split labeled data into train/test
        $split = $pre_svc->splitData($labeled, $split_ratio, $seed);
        $train_raw = $split['train'];
        $test_raw  = $split['test'];

        if (empty($test_raw)) {
            throw new \Exception('Data testing kosong. Kurangi rasio training atau tambah data berlabel.');
        }

        // Adjust K if needed
        $train_count = count($train_raw);
        if ($k > $train_count) {
            $k = $train_count;
        }

        // 4. Normalize train and test sets (using params from ALL labeled data)
        $train_norm = $pre_svc->normalizeData($train_raw, $norm_params);
        $test_norm  = $pre_svc->normalizeData($test_raw,  $norm_params);

        // 5. Run KNN on test set → evaluate
        $eval_predictions = [];
        $test_results     = [];

        foreach ($test_norm as $test_row) {
            $result = $this->classify($test_row, $train_norm, $k);
            $eval_predictions[] = [
                'actual'    => $test_row['label'],
                'predicted' => $result['prediction'],
            ];
            $test_results[] = [
                'row'    => $test_row,
                'result' => $result,
            ];
        }

        // 6. Calculate metrics
        $metrics = $eval_svc->calculate($eval_predictions);
        $metrics['k_value']     = $k;
        $metrics['split_ratio'] = $split_ratio . '/' . (100 - $split_ratio);
        $metrics['seed']        = $seed;
        $metrics['train_count'] = $train_count;
        $metrics['test_count']  = count($test_raw);

        // 7. Save knn_process record to DB
        $user_id    = $_SESSION['user']['id'] ?? null;
        $process_id = $this->saveProcess($metrics, (int)$user_id);

        // 8. Save norm params to knn_norm_params
        $this->saveNormParams($process_id, $norm_params);

        // 9. Classify ALL products (including unlabeled) using final model
        //    (all labeled data as training set)
        $all_labeled_norm = $pre_svc->normalizeData($labeled, $norm_params);

        // Also get all products (labeled + unlabeled)
        $all_products = $this->pdo->query(
            "SELECT id, name, sold, rating, price_now, label FROM products ORDER BY id ASC"
        )->fetchAll();
        $all_normalized = $pre_svc->normalizeData($all_products, $norm_params);

        // 10. Save predictions to knn_predictions table
        $predictions_to_save = [];

        // Map test rows by product id for is_test_data flag
        $test_ids = array_flip(array_column($test_raw, 'id'));

        foreach ($all_normalized as $prod_row) {
            $result = $this->classify($prod_row, $all_labeled_norm, $k);

            $is_test     = isset($test_ids[$prod_row['id']]) ? 1 : 0;
            $actual_lbl  = ($prod_row['label'] !== null && $prod_row['label'] !== '')
                ? $prod_row['label']
                : null;
            $is_correct  = null;
            if ($is_test && $actual_lbl !== null) {
                $is_correct = ($result['prediction'] === $actual_lbl) ? 1 : 0;
            }

            // Build neighbors JSON for storage
            $neighbors_json = [];
            foreach ($result['neighbors'] as $nb) {
                $neighbors_json[] = [
                    'product_id'   => $nb['product']['id'],
                    'product_name' => $nb['product']['name'] ?? '',
                    'distance'     => round($nb['distance'], 8),
                    'label'        => $nb['label'],
                ];
            }

            $predictions_to_save[] = [
                'process_id'        => $process_id,
                'product_id'        => $prod_row['id'],
                'prediction'        => $result['prediction'],
                'normalized_sold'   => round($prod_row['sold_n'],   8),
                'normalized_rating' => round($prod_row['rating_n'], 8),
                'normalized_price'  => round($prod_row['price_n'],  8),
                'neighbors_json'    => json_encode($neighbors_json),
                'is_test_data'      => $is_test,
                'actual_label'      => $actual_lbl,
                'is_correct'        => $is_correct,
            ];
        }

        $this->savePredictions($process_id, $predictions_to_save);

        // 11. Update products.prediction and products.prediction_process_id
        $upd = $this->pdo->prepare(
            "UPDATE products SET prediction = ?, prediction_process_id = ? WHERE id = ?"
        );
        foreach ($predictions_to_save as $ps) {
            $upd->execute([$ps['prediction'], $process_id, $ps['product_id']]);
        }

        // 12. Return all results
        return [
            'process_id'   => $process_id,
            'metrics'      => $metrics,
            'norm_params'  => $norm_params,
            'train_count'  => $train_count,
            'test_count'   => count($test_raw),
            'k'            => $k,
            'split_ratio'  => $split_ratio,
            'seed'         => $seed,
            'predictions'  => $predictions_to_save,
            'test_results' => $test_results,
        ];
    }

    /**
     * Save a completed process to DB.
     */
    private function saveProcess(array $metrics, int $created_by): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO knn_processes
             (k_value, split_ratio, random_seed, train_count, test_count,
              accuracy, precision_score, recall_score, f1_score, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $metrics['k_value'],
            $metrics['split_ratio'],
            $metrics['seed'],
            $metrics['train_count'],
            $metrics['test_count'],
            $metrics['accuracy'],
            $metrics['macro']['precision'],
            $metrics['macro']['recall'],
            $metrics['macro']['f1'],
            $created_by,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Save normalization parameters to knn_norm_params.
     */
    private function saveNormParams(int $process_id, array $params): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO knn_norm_params (process_id, feature, min_val, max_val)
             VALUES (?, ?, ?, ?)"
        );
        foreach ($params as $feature => $vals) {
            $stmt->execute([
                $process_id,
                $feature,
                $vals['min'],
                $vals['max'],
            ]);
        }
    }

    /**
     * Save predictions to knn_predictions table.
     */
    private function savePredictions(int $process_id, array $predictions): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO knn_predictions
             (process_id, product_id, prediction, normalized_sold, normalized_rating,
              normalized_price, neighbors_json, is_test_data, actual_label, is_correct)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($predictions as $p) {
            $stmt->execute([
                $p['process_id'],
                $p['product_id'],
                $p['prediction'],
                $p['normalized_sold'],
                $p['normalized_rating'],
                $p['normalized_price'],
                $p['neighbors_json'],
                $p['is_test_data'],
                $p['actual_label'],
                $p['is_correct'],
            ]);
        }
    }
}
