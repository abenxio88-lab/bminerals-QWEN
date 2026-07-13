const activeLocks = [];
const nonPassiveCapture = { capture: true, passive: false };
let lastTouchY = null;
let pausedLenis = null;

function getActiveModal() {
  return activeLocks[activeLocks.length - 1]?.modal || null;
}

function getEventPath(event) {
  if (typeof event.composedPath === 'function') return event.composedPath();

  const path = [];
  let node = event.target;
  while (node) {
    path.push(node);
    node = node.parentNode;
  }
  return path;
}

function getScrollableElement(event, modal) {
  for (const node of getEventPath(event)) {
    if (!(node instanceof Element)) continue;
    if (!modal.contains(node)) break;

    const overflowY = window.getComputedStyle(node).overflowY;
    if (/(auto|scroll)/.test(overflowY) && node.scrollHeight > node.clientHeight + 1) {
      return node;
    }
  }
  return null;
}

function shouldBlockScroll(scroller, deltaY) {
  if (!scroller) return true;

  const atTop = scroller.scrollTop <= 0;
  const atBottom = scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - 1;
  return (deltaY < 0 && atTop) || (deltaY > 0 && atBottom);
}

function handleWheel(event) {
  const modal = getActiveModal();
  if (!modal || event.ctrlKey) return;

  const scroller = getScrollableElement(event, modal);
  if (shouldBlockScroll(scroller, event.deltaY)) event.preventDefault();
}

function handleTouchStart(event) {
  lastTouchY = event.touches.length === 1 ? event.touches[0].clientY : null;
}

function handleTouchMove(event) {
  const modal = getActiveModal();
  if (!modal || event.touches.length !== 1 || lastTouchY === null) return;

  const currentY = event.touches[0].clientY;
  const fingerDeltaY = currentY - lastTouchY;
  lastTouchY = currentY;

  const scroller = getScrollableElement(event, modal);
  if (shouldBlockScroll(scroller, -fingerDeltaY)) event.preventDefault();
}

function attachScrollGuards() {
  document.addEventListener('wheel', handleWheel, nonPassiveCapture);
  document.addEventListener('touchstart', handleTouchStart, { capture: true, passive: true });
  document.addEventListener('touchmove', handleTouchMove, nonPassiveCapture);
}

function detachScrollGuards() {
  document.removeEventListener('wheel', handleWheel, nonPassiveCapture);
  document.removeEventListener('touchstart', handleTouchStart, true);
  document.removeEventListener('touchmove', handleTouchMove, nonPassiveCapture);
  lastTouchY = null;
}

function lockModalScroll(modal) {
  const token = Symbol('image-modal-lock');
  const isFirstLock = activeLocks.length === 0;
  activeLocks.push({ token, modal });

  if (isFirstLock) {
    const usesTouchScrolling = window.matchMedia?.('(hover: none), (pointer: coarse)')?.matches;
    pausedLenis = usesTouchScrolling ? null : window.__bmLenis;
    pausedLenis?.stop?.();
    attachScrollGuards();
  }

  return () => {
    const lockIndex = activeLocks.findIndex((lock) => lock.token === token);
    if (lockIndex === -1) return;

    activeLocks.splice(lockIndex, 1);
    if (activeLocks.length) return;

    detachScrollGuards();
    const lenis = pausedLenis;
    pausedLenis = null;
    if (lenis) window.requestAnimationFrame(() => lenis.start?.());
  };
}

function focusWithoutScroll(element) {
  if (!element?.isConnected || typeof element.focus !== 'function') return;

  try {
    element.focus({ preventScroll: true });
  } catch {
    // A plain focus fallback can move mobile viewports.
  }
}

export function createImageModalGuard(lockClass) {
  let releaseScrollLock = null;
  let trigger = null;
  let restoreFocus = false;
  let activeCloseButton = null;

  const syncCloseButtonToVisualViewport = () => {
    if (!activeCloseButton) return;

    const offsetLeft = window.visualViewport?.offsetLeft || 0;
    const offsetTop = window.visualViewport?.offsetTop || 0;
    activeCloseButton.style.transform = `translate(${offsetLeft}px, ${offsetTop}px)`;
  };

  const startCloseButtonTracking = (closeButton) => {
    activeCloseButton = closeButton;
    syncCloseButtonToVisualViewport();
    window.visualViewport?.addEventListener('resize', syncCloseButtonToVisualViewport);
    window.visualViewport?.addEventListener('scroll', syncCloseButtonToVisualViewport);
  };

  const stopCloseButtonTracking = () => {
    window.visualViewport?.removeEventListener('resize', syncCloseButtonToVisualViewport);
    window.visualViewport?.removeEventListener('scroll', syncCloseButtonToVisualViewport);
    activeCloseButton?.style.removeProperty('transform');
    activeCloseButton = null;
  };

  return {
    isOpen() {
      return Boolean(releaseScrollLock);
    },

    open({ modal, trigger: nextTrigger = null, restoreFocus: shouldRestore = false, closeButton = null }) {
      if (!modal || releaseScrollLock) return false;

      trigger = nextTrigger;
      restoreFocus = shouldRestore;
      modal.setAttribute('data-lenis-prevent', '');
      modal.setAttribute('data-lenis-prevent-wheel', '');
      modal.setAttribute('data-lenis-prevent-touch', '');
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.documentElement.classList.add(lockClass);
      document.body.classList.add(lockClass);
      releaseScrollLock = lockModalScroll(modal);
      startCloseButtonTracking(closeButton);

      if (restoreFocus) focusWithoutScroll(closeButton);
      return true;
    },

    close({ modal }) {
      if (!modal || !releaseScrollLock) return false;

      if (modal.contains(document.activeElement)) document.activeElement.blur();
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.documentElement.classList.remove(lockClass);
      document.body.classList.remove(lockClass);
      stopCloseButtonTracking();

      const release = releaseScrollLock;
      releaseScrollLock = null;
      release();

      if (restoreFocus) focusWithoutScroll(trigger);
      trigger = null;
      restoreFocus = false;
      return true;
    },
  };
}
