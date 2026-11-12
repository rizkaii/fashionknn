      </main>
    </div>
  </div>
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= filemtime(BASE_PATH . '/public/assets/js/app.js') ?>"></script>
  <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
