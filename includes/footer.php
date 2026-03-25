<!--
  ephemeralREST — Swiss Ephemeris REST API
  Copyright (C) 2026  ephemeralREST contributors
  GNU Affero General Public License v3 — https://www.gnu.org/licenses/agpl-3.0.html
  AGPL v3 used for compatibility with Swiss Ephemeris (Astrodienst AG)
-->

    </div><!-- /.main__inner -->
  </main><!-- /.main -->

</div><!-- /.layout -->

<script>
// Auto-dismiss flash messages
document.addEventListener('DOMContentLoaded', () => {
  const flash = document.querySelector('.flash');
  if (flash) {
    setTimeout(() => {
      flash.style.opacity = '0';
      flash.style.transform = 'translateY(-8px)';
      setTimeout(() => flash.remove(), 300);
    }, 4000);
  }

  // Confirm dangerous actions
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  // Copy to clipboard
  document.querySelectorAll('[data-copy]').forEach(el => {
    el.addEventListener('click', () => {
      navigator.clipboard.writeText(el.dataset.copy).then(() => {
        const orig = el.textContent;
        el.textContent = 'Copied';
        setTimeout(() => el.textContent = orig, 1500);
      });
    });
  });
});
</script>

  <footer class="portal-footer">
    <span>ephemeralREST &copy; <?= date('Y') ?></span>
    <span class="portal-footer__sep">·</span>
    <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" rel="noopener">AGPL v3 Licensed</a>
    <span class="portal-footer__sep">·</span>
    <span>AGPL v3 licence maintained for compatibility with Swiss Ephemeris (Astrodienst AG)</span>
    <span class="portal-footer__sep">·</span>
    <a href="https://github.com/gmelh/ephemeralREST" target="_blank" rel="noopener">Source on GitHub</a>
  </footer>

</body>
</html>
