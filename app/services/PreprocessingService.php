<?php
class PreprocessingService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get all products with labels (training candidates).
     */
    public function getLabeledData(): array {
        $stmt = $this->pdo->query(
            "SELECT id, name, sold, rating, price_now, label
             FROM products
             WHERE label IS NOT NULL AND label != ''
             ORDER BY id ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Get dataset statistics.
     * Returns: total, labeled, unlabeled, by_label[], duplicates, missing_values
     */
    public function getDataStats(): array {
        $total = (int)$this->pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $labeled = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM products WHERE label IS NOT NULL AND label != ''"
        )->fetchColumn();
        $unlabeled = $total - $labeled;

        // Per-label counts
        $rows = $this->pdo->query(
            "SELECT label, COUNT(*) as cnt FROM products
             WHERE label IS NOT NULL AND label != ''
             GROUP BY label"
        )->fetchAll();
        $by_label = [];
        foreach ($rows as $r) {
            $by_label[$r['label']] = (int)$r['cnt'];
        }

        // Duplicates: same (sold, rating, price_now) with same label
        $dup = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM (
                SELECT sold, rating, price_now, label, COUNT(*) as cnt
                FROM products
                WHERE label IS NOT NULL AND label != ''
                GROUP BY sold, rating, price_now, label
                HAVING COUNT(*) > 1
             ) t"
        )->fetchColumn();

        // Missing values: products where sold IS NULL or rating IS NULL or price_now IS NULL
        $missing = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM products
             WHERE sold IS NULL OR rating IS NULL OR price_now IS NULL"
        )->fetchColumn();

        return [
            'total'          => $total,
            'labeled'        => $labeled,
            'unlabeled'      => $unlabeled,
            'by_label'       => $by_label,
            'duplicates'     => $dup,
            'missing_values' => $missing,
        ];
    }

    /**
     * Calculate min-max params from training data.
     * Returns: ['sold'=>['min','max'], 'rating'=>['min','max'], 'price_now'=>['min','max']]
     */
    public function calcNormParams(array $data): array {
        if (empty($data)) {
            return [
                'sold'      => ['min' => 0, 'max' => 0],
                'rating'    => ['min' => 0, 'max' => 0],
                'price_now' => ['min' => 0, 'max' => 0],
            ];
        }
        $sold_vals      = array_column($data, 'sold');
        $rating_vals    = array_column($data, 'rating');
        $price_vals     = array_column($data, 'price_now');
        return [
            'sold'      => ['min' => (float)min($sold_vals),    'max' => (float)max($sold_vals)],
            'rating'    => ['min' => (float)min($rating_vals),  'max' => (float)max($rating_vals)],
            'price_now' => ['min' => (float)min($price_vals),   'max' => (float)max($price_vals)],
        ];
    }

    /**
     * Normalize a single value using min-max scaling.
     */
    public function normalize(float $val, float $min, float $max): float {
        if ($max == $min) return 0.0;
        return ($val - $min) / ($max - $min);
    }

    /**
     * Normalize an array of products using given params.
     * Adds sold_n, rating_n, price_n fields to each row.
     */
    public function normalizeData(array $data, array $params): array {
        $result = [];
        foreach ($data as $row) {
            $row['sold_n']   = $this->normalize(
                (float)$row['sold'],
                $params['sold']['min'],
                $params['sold']['max']
            );
            $row['rating_n'] = $this->normalize(
                (float)$row['rating'],
                $params['rating']['min'],
                $params['rating']['max']
            );
            $row['price_n']  = $this->normalize(
                (float)$row['price_now'],
                $params['price_now']['min'],
                $params['price_now']['max']
            );
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Split data into train/test with stratification.
     * $ratio = 70 or 80 (percent for training)
     * $seed = random seed for reproducibility
     * Returns ['train'=>[], 'test'=>[]]
     */
    public function splitData(array $data, int $ratio, int $seed): array {
        mt_srand($seed);

        // Group by label
        $groups = [];
        foreach ($data as $row) {
            $label = $row['label'] ?? 'unknown';
            $groups[$label][] = $row;
        }

        $train = [];
        $test  = [];

        foreach ($groups as $label => $rows) {
            // Shuffle within group
            $keys = array_keys($rows);
            shuffle($keys);
            $shuffled = [];
            foreach ($keys as $k) {
                $shuffled[] = $rows[$k];
            }

            $n_train = (int)round(count($shuffled) * $ratio / 100);
            // Ensure at least 1 in each set if possible
            if ($n_train >= count($shuffled) && count($shuffled) > 1) {
                $n_train = count($shuffled) - 1;
            }
            if ($n_train < 1) {
                $n_train = count($shuffled); // all go to train if only 1
            }

            $train_part = array_slice($shuffled, 0, $n_train);
            $test_part  = array_slice($shuffled, $n_train);

            foreach ($train_part as $r) $train[] = $r;
            foreach ($test_part  as $r) $test[]  = $r;
        }

        return ['train' => $train, 'test' => $test];
    }

    /**
     * Get preview of normalization (first 10 labeled rows).
     * Returns array of rows with original + normalized values.
     */
    public function getNormalizationPreview(array $data, array $params): array {
        $preview = array_slice($data, 0, 10);
        return $this->normalizeData($preview, $params);
    }
}
