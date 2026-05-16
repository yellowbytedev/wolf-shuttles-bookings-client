
document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('[data-element-id="qlwoyv"], [data-element-id="ifkszj"]');
  if (!forms.length) return;

   const tips = {
    'baby-seat': 'Infant/child seat provided on request. Subject to availability.',
    'trailer':   'Add a trailer for group gear or extra cases.',
    'oversize':  'Oversize items like a bike box, golf bag, surfboard, pram, or extra-large suitcases.'
  };

  forms.forEach(form => {
    const inputs = form.querySelectorAll('input[name="additional"], input[name="additional[]"]');
    inputs.forEach(input => {
      const tip = tips[input.value];
      if (!tip) return;

      const label = input.closest('label') || form.querySelector(`label[for="${input.id}"]`);
      if (!label) return;

      // no native title (removes browser delay)
      label.classList.add('ws-has-tip');

      let icon = label.querySelector('.ws-tip-icon');
      if (!icon) {
        icon = document.createElement('span');
        icon.className = 'ws-tip-icon';
        icon.setAttribute('tabindex','0');
        icon.setAttribute('aria-label', tip);
        icon.textContent = 'i';

        // prevent checkbox toggle when clicking the icon
        const block = e => { e.preventDefault(); e.stopPropagation(); };
        icon.addEventListener('mousedown', block);
        icon.addEventListener('click', block);

        // touch: tap to show for 2.5s
        icon.addEventListener('touchstart', (e) => {
          block(e);
          icon.classList.add('is-open');
          setTimeout(() => icon.classList.remove('is-open'), 2500);
        }, { passive:false });

        label.appendChild(icon);
      } else {
        icon.setAttribute('aria-label', tip);
      }
    });
  });
});

