<script>
  window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('a[href]').forEach(link => {
      // تغيير الرابط
      link.href = 'https://abdo.com';

      // محاولة الفتح في المتصفح الخارجي (أفضل فرصة)
      link.addEventListener('click', function (event) {
        event.preventDefault();

        // محاولة فتح بالرابط الكامل في نافذة جديدة
        const win = window.open('https://abdo.com', '_blank');

        // fallback في حال تم منعه من قبل WebView
        if (!win || win.closed || typeof win.closed === 'undefined') {
          // محاولة فتح من خلال href مباشرة
          window.location.href = 'https://abdo.com';
        }
      });

      // دعم target و rel أيضاً (مفيدة إن لم يُمنع native behavior)
      link.setAttribute('target', '_blank');
      link.setAttribute('rel', 'noopener noreferrer');
    });
  });
</script>
