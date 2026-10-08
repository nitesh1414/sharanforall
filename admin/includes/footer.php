  </main>
</div>
</div>
<script>
// Confirm delete
document.querySelectorAll('form.del-form').forEach(f => {
  f.addEventListener('submit', e => {
    if (!confirm('Are you sure you want to delete this item? This cannot be undone.')) e.preventDefault();
  });
});

// Language tabs (for content forms)
document.querySelectorAll('.lang-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    const lang = tab.dataset.lang;
    const parent = tab.closest('form') || tab.closest('.card') || document;
    parent.querySelectorAll('.lang-tab').forEach(t => t.classList.toggle('active', t.dataset.lang === lang));
    parent.querySelectorAll('.lang-panel').forEach(p => p.classList.toggle('active', p.dataset.langPanel === lang));
  });
});

// Show width × height of an image as soon as it is chosen for upload
document.querySelectorAll('input[type="file"][accept*="image"]').forEach(input => {
  input.addEventListener('change', () => {
    let live = input.parentElement && input.parentElement.querySelector('.img-size-live');
    if (!live) {
      live = document.createElement('p');
      live.className = 'help img-size-live';
      input.insertAdjacentElement('afterend', live);
    }
    const file = input.files && input.files[0];
    if (!file) { live.hidden = true; live.textContent = ''; return; }
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      const recW = parseInt(input.dataset.recW || '0', 10);
      const recH = parseInt(input.dataset.recH || '0', 10);
      const kb = Math.max(1, Math.round(file.size / 1024));
      let note = 'This image: <strong>' + img.naturalWidth + ' × ' + img.naturalHeight + ' px</strong> (' + kb + ' KB).';
      if (recW && recH) {
        const match = img.naturalWidth === recW && img.naturalHeight === recH;
        note += match
          ? ' ✓ Matches the recommended size.'
          : ' Recommended is <code>' + recW + ' × ' + recH + ' px</code> — it will still display in full, centred.';
      }
      live.innerHTML = note;
      live.hidden = false;
      URL.revokeObjectURL(url);
    };
    img.onerror = () => { live.hidden = true; URL.revokeObjectURL(url); };
    img.src = url;
  });
});
</script>
</body>
</html>
