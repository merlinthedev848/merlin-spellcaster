  </main>
</div><!-- /.ml-60 -->

<script>
(() => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  if (!token) return;
  document.querySelectorAll('form[method]').forEach((form) => {
    if ((form.getAttribute('method') || '').toLowerCase() !== 'post') return;
    if (form.querySelector('input[name="_csrf"]')) return;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = '_csrf';
    input.value = token;
    form.prepend(input);
  });
})();
</script>
<?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
