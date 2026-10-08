<?php $BU = BASE_URL; ?>
<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="foot-grid">
      <div class="foot">
        <div class="logo">
          <div class="logo-mark"><img src="<?= $BU ?>images/logo.png" alt="Sharan Foundation logo"></div>
          <div class="logo-text">
            <span class="logo-name">Sharan Foundation</span>
            <small><?= e(t('site_tagline')) ?></small>
          </div>
        </div>
        <p><?= e(get_setting('about_short')) ?></p>
        <div class="socials">
          <a href="<?= e(get_setting('facebook','#')) ?>" title="Facebook">f</a>
          <a href="<?= e(get_setting('instagram','#')) ?>" title="Instagram">◉</a>
          <a href="<?= e(get_setting('youtube','#')) ?>" title="YouTube">▶</a>
          <a href="<?= e(get_setting('twitter','#')) ?>" title="Twitter">✕</a>
        </div>
      </div>
      <div class="foot"><h4><?= e(t('quick_links')) ?></h4><ul>
        <li><a href="<?= $BU ?>pages/about.php"><?= e(t('nav_about')) ?></a></li>
        <li><a href="<?= $BU ?>pages/programs.php"><?= e(t('nav_programs')) ?></a></li>
        <li><a href="<?= $BU ?>pages/projects.php"><?= e(t('nav_projects')) ?></a></li>
        <li><a href="<?= $BU ?>pages/fundraisers.php"><?= e(t('nav_fundraisers')) ?></a></li>
        <li><a href="<?= $BU ?>pages/gallery.php"><?= e(t('nav_gallery')) ?></a></li>
        <li><a href="<?= $BU ?>pages/blog.php"><?= e(t('nav_blog')) ?></a></li>
      </ul></div>
      <div class="foot"><h4><?= e(t('nav_programs')) ?></h4><ul>
        <li><a href="<?= $BU ?>pages/programs.php#child-education">Child Education</a></li>
        <li><a href="<?= $BU ?>pages/programs.php#women">Women Empowerment</a></li>
        <li><a href="<?= $BU ?>pages/programs.php#oldage">Old Age Home</a></li>
        <li><a href="<?= $BU ?>pages/programs.php#girls-hostel">Hostels</a></li>
        <li><a href="<?= $BU ?>pages/programs.php#bible-college">Bible College</a></li>
      </ul></div>
      <div class="foot"><h4><?= e(t('get_involved')) ?></h4><ul>
        <li><a href="<?= $BU ?>pages/volunteer.php"><?= e(t('nav_volunteer')) ?></a></li>
        <li><a href="<?= $BU ?>pages/partner.php"><?= e(t('nav_partner')) ?></a></li>
        <li><a href="<?= $BU ?>pages/donate.php"><?= e(t('nav_donate')) ?></a></li>
        <li><a href="<?= $BU ?>pages/contact.php"><?= e(t('nav_contact')) ?></a></li>
      </ul>
      <form action="<?= $BU ?>api/submit_newsletter.php" method="post" style="margin-top:1rem">
        <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
        <input type="email" name="email" placeholder="<?= e(t('email_address')) ?>" required style="width:100%;padding:.5rem;border-radius:6px;border:none;margin-bottom:.4rem;color:#333">
        <button class="btn btn-primary" style="width:100%;padding:.5rem"><?= e(t('btn_subscribe')) ?></button>
      </form>
      </div>
    </div>
    <div class="copy">© <?= date('Y') ?> <span>Sharan Foundation</span>. <?= e(t('all_rights')) ?>. &nbsp;|&nbsp; <?= e(t('serving_love')) ?></div>
  </div>
</footer>
<script src="<?= $BU ?>js/main.js"></script>
<script src="<?= $BU ?>js/pwa.js" defer></script>
</body>
</html>
