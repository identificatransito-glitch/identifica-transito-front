/* Integra as telas atualizadas com a navegação do painel já publicado. */
(function () {
  var documentPath = normalize(location.pathname);
  function normalize(path) { return path.replace(/\/+$/, '').toLowerCase() || '/'; }
  function isPublishedPage(url) {
    return url.origin === location.origin &&
      (url.pathname === '/' || url.pathname === '/index.html' ||
       /^\/auth\/(register|login|forgot-password|reset-password)\/?$/i.test(url.pathname));
  }
  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey ||
        event.ctrlKey || event.shiftKey || event.altKey) return;
    var anchor = event.target.closest && event.target.closest('a[href]');
    if (!anchor || anchor.hasAttribute('download') ||
        (anchor.target && anchor.target !== '_self')) return;
    var url = new URL(anchor.href, location.href);
    if (!isPublishedPage(url)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    location.assign(url.href);
  }, true);

  /* O logout usa o roteador, sem clique em link. Carrega o HTML novo ao sair. */
  ['pushState', 'replaceState'].forEach(function (method) {
    var original = history[method];
    history[method] = function (state, title, destination) {
      if (destination != null) {
        var url = new URL(destination, location.href);
        if (isPublishedPage(url) && normalize(url.pathname) !== documentPath) {
          location.assign(url.href);
          return;
        }
      }
      return original.apply(this, arguments);
    };
  });
  window.addEventListener('popstate', function () {
    var url = new URL(location.href);
    if (isPublishedPage(url) && normalize(url.pathname) !== documentPath) {
      location.replace(url.href);
    }
  });
})();
