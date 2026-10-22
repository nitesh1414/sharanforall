<?php
/**
 * Lightweight WYSIWYG editor for admin forms (no external dependencies).
 *
 * Usage:
 *   require_once __DIR__ . '/editor.php';
 *   editor_field('content', $row['content'], 'Content', 380);
 *
 * Renders a formatting toolbar (bold / italic / underline, headings,
 * lists, quote, link, image upload) over a contenteditable area. The
 * resulting HTML is synced into a hidden <textarea name="..."> so the
 * normal form POST handling stays unchanged. Images are uploaded via
 * admin/blog_image_upload.php and inserted at the cursor.
 */

/**
 * Render the editor field.
 *
 * @param string $name   Form field name (becomes the hidden textarea's name).
 * @param string $value  Initial HTML content (admin-authored — printed raw
 *                       into the editing area, escaped into the textarea).
 * @param string $label  Field label (printed raw — developer-provided).
 * @param int    $height Minimum height of the editing area in px.
 */
function editor_field($name, $value, $label = 'Content', $height = 380){
    static $scripts_printed = false;
    $id = 'wysiwyg_' . preg_replace('/[^a-zA-Z0-9]/', '', $name);
    $height = (int)$height;
    ?>
    <div class="form-group">
      <label><?= $label ?></label>
      <div class="wysiwyg" id="<?= $id ?>">
        <div class="wysiwyg-toolbar">
          <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
          <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
          <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
          <button type="button" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
          <span class="wysiwyg-sep"></span>
          <button type="button" data-block="h2" title="Heading 2">H2</button>
          <button type="button" data-block="h3" title="Heading 3">H3</button>
          <button type="button" data-block="p" title="Paragraph">¶</button>
          <button type="button" data-block="blockquote" title="Quote">❝ Quote</button>
          <span class="wysiwyg-sep"></span>
          <button type="button" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
          <button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
          <span class="wysiwyg-sep"></span>
          <button type="button" data-cmd="createLink" title="Insert link">🔗 Link</button>
          <button type="button" data-cmd="unlink" title="Remove link">✕ Link</button>
          <button type="button" data-img title="Upload &amp; insert image">🖼️ Image</button>
          <span class="wysiwyg-sep"></span>
          <button type="button" data-cmd="removeFormat" title="Clear formatting">⌫ Clear</button>
          <button type="button" data-cmd="undo" title="Undo">↶</button>
          <button type="button" data-cmd="redo" title="Redo">↷</button>
        </div>
        <div class="wysiwyg-area" contenteditable="true" style="min-height:<?= $height ?>px"><?= $value ?></div>
        <textarea name="<?= e($name) ?>" class="wysiwyg-source" tabindex="-1" aria-hidden="true"><?= e($value) ?></textarea>
        <p class="help">Format the post with the toolbar — headings, lists, quotes and links — and use 🖼️ Image to upload pictures straight into the content.</p>
      </div>
    </div>
    <?php if (!$scripts_printed): $scripts_printed = true; ?>
    <script>
    (function(){
      if (window.__wysiwygInit) return;
      window.__wysiwygInit = true;
      document.addEventListener('DOMContentLoaded', function(){
        var uploadUrl = <?= json_encode(ADMIN_URL . 'blog_image_upload.php') ?>;
        var csrfToken = <?= json_encode(csrf_token()) ?>;

        document.querySelectorAll('.wysiwyg').forEach(function(wrap){
          var area = wrap.querySelector('.wysiwyg-area');
          var ta   = wrap.querySelector('.wysiwyg-source');
          if (!area || !ta) return;

          // Keep the hidden textarea in sync with the editing area
          var sync = function(){ ta.value = area.innerHTML; };
          area.addEventListener('input', sync);
          area.addEventListener('keyup', sync);
          area.addEventListener('paste', function(){ setTimeout(sync, 0); });

          var form = wrap.closest('form');
          if (form) form.addEventListener('submit', function(e){
            sync();
            if (area.textContent.replace(/\s/g, '') === '' && !area.querySelector('img')) {
              e.preventDefault();
              alert('Please write some content before saving.');
              area.focus();
            }
          });

          // Inline formatting commands
          wrap.querySelectorAll('[data-cmd]').forEach(function(btn){
            btn.addEventListener('click', function(){
              area.focus();
              var cmd = btn.getAttribute('data-cmd');
              if (cmd === 'createLink') {
                var url = prompt('Enter the link URL:', 'https://');
                if (url) document.execCommand('createLink', false, url);
              } else {
                document.execCommand(cmd, false, null);
              }
              sync();
            });
          });

          // Block formatting (headings, paragraph, quote)
          wrap.querySelectorAll('[data-block]').forEach(function(btn){
            btn.addEventListener('click', function(){
              area.focus();
              document.execCommand('formatBlock', false, btn.getAttribute('data-block'));
              sync();
            });
          });

          // Image upload — inserts the uploaded image at the cursor
          var imgBtn = wrap.querySelector('[data-img]');
          if (imgBtn) imgBtn.addEventListener('click', function(){
            var inp = document.createElement('input');
            inp.type = 'file';
            inp.accept = 'image/*';
            inp.addEventListener('change', function(){
              if (!inp.files || !inp.files.length) return;
              var fd = new FormData();
              fd.append('image', inp.files[0]);
              fd.append('csrf', csrfToken);
              imgBtn.disabled = true;
              fetch(uploadUrl, { method: 'POST', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(j){
                  if (j && j.ok && j.url) {
                    area.focus();
                    document.execCommand('insertImage', false, j.url);
                    sync();
                  } else {
                    alert((j && j.error) || 'Image upload failed. Please try again.');
                  }
                })
                .catch(function(){ alert('Image upload failed. Please try again.'); })
                .then(function(){ imgBtn.disabled = false; });
            });
            inp.click();
          });
        });
      });
    })();
    </script>
    <?php endif;
}
