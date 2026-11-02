(() => {
  if (window.__rescuePieTooltipInit || !document.querySelector('.pie-wrap')) return;
  window.__rescuePieTooltipInit = true;
  document.documentElement.classList.add('rescue-pie-tooltip--enhanced');
  const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
  const position = (tooltip, event) => {
    const gap = 14;
    const rect = tooltip.getBoundingClientRect();
    let x = event.clientX + gap;
    let y = event.clientY + gap;
    if (x + rect.width > window.innerWidth - 8) x = event.clientX - rect.width - gap;
    if (y + rect.height > window.innerHeight - 8) y = event.clientY - rect.height - gap;
    tooltip.style.left = `${clamp(x, 8, Math.max(8, window.innerWidth - rect.width - 8))}px`;
    tooltip.style.top = `${clamp(y, 8, Math.max(8, window.innerHeight - rect.height - 8))}px`;
  };
  const getTooltip = (target) => {
    const slice = target.closest('.pie-slice[data-category-uid]');
    const wrap = slice?.closest('.pie-wrap');
    return wrap?.querySelector(`.pie-tooltip[data-category-uid="${slice.getAttribute('data-category-uid')}"]`);
  };
  document.addEventListener('mouseover', (event) => { const tooltip = getTooltip(event.target); if (tooltip) { tooltip.style.display = 'block'; position(tooltip, event); } });
  document.addEventListener('mousemove', (event) => { const tooltip = getTooltip(event.target); if (tooltip?.style.display === 'block') position(tooltip, event); });
  document.addEventListener('mouseout', (event) => { const tooltip = getTooltip(event.target); if (tooltip) tooltip.style.display = 'none'; });
})();
