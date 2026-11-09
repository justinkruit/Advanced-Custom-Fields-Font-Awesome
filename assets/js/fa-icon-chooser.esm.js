import 'https://cdn.jsdelivr.net/npm/@fortawesome/fa-icon-chooser@0.11.0/dist/fa-icon-chooser/fa-icon-chooser.esm.js';

(function ($) {
  // Font Awesome API endpoint for GraphQL
  const API_URL = 'https://api.fontawesome.com';

  let iconSets = [];
  let previewEl = null;
  let inputEl = null;
  let container = null;

  /*
   * ------------------------------------------------------------------
   * Request caching
   * ------------------------------------------------------------------
   * The <fa-icon-chooser> element is created fresh every time the picker
   * opens, so its own in-memory caches are lost between opens. Without the
   * caching below, every open in kit mode costs three sequential
   * admin-ajax -> api.fontawesome.com round trips (KitRevision, KitMetadata,
   * ShowcaseIcons) before any icon is shown.
   *
   * - Responses the component marks as cacheable ({ cache: true }: kit
   *   metadata and showcase icons) are kept in memory and persisted in
   *   localStorage, scoped to the kit token. They are invalidated as soon
   *   as the KitRevision probe reports a different kit revision or
   *   showcase cache key, so a changed kit is never served stale data.
   * - The KitRevision probe itself is re-used for a few minutes within the
   *   same page instead of being repeated on every open.
   * - Search results are kept in memory for the lifetime of the page.
   * - Identical requests that are already in flight are shared.
   * - Once the page has loaded, the kit data is fetched in the background
   *   so the first open of the picker is (nearly) instant as well.
   */
  const STORAGE_KEY = 'acffa_fa_query_cache';
  const STORAGE_VERSION = 1;
  const REVISION_TTL = 5 * 60 * 1000;
  const MEMORY_CACHE_LIMIT = 200;

  const QUERY_KIT_REVISION =
    'query KitRevision($token: String!) { me { kit(token: $token) { kitRevision showcaseCacheKey } } }';
  const QUERY_KIT_METADATA =
    'query KitMetadata($token: String!) { me { kit(token: $token) { kitRevision showcaseCacheKey familyStylesPaginated(page: 1, pageSize: 50) { familyStyles { familyStyle { family style prefix } } } version technologySelected licenseSelected name permits { embedProSvg { prefix family } } release { version } iconUploads { name unicode version width height pathData } } } }';
  const QUERY_SHOWCASE_ICONS =
    'query ShowcaseIcons($token: String!, $selector: FamilyStyleSelector!) { me { kit(token: $token) { showcaseIcons(selector: $selector, page: 1, pageSize: 80, limitPerFamilyStyle: 80) { page pageSize totalIconVariantCount totalPageCount iconVariants { name unicodeHex familyStyle { family style prefix } } } } } }';

  const memoryCache = new Map();
  const inflightQueries = new Map();
  const inflightUrls = new Map();
  let revisionPromise = null;
  let revisionFetchedAt = 0;
  let persistentStore = loadPersistentStore();

  function normalizeQuery(query) {
    return String(query || '').replace(/\s+/g, ' ').trim();
  }

  function operationName(query) {
    const match = /^query\s+(\w+)/.exec(query);
    return match ? match[1] : '';
  }

  function stableStringify(value) {
    if (Array.isArray(value)) {
      return '[' + value.map(stableStringify).join(',') + ']';
    }
    if (value && typeof value === 'object') {
      return (
        '{' +
        Object.keys(value)
          .sort()
          .map(key => JSON.stringify(key) + ':' + stableStringify(value[key]))
          .join(',') +
        '}'
      );
    }
    return JSON.stringify(value === undefined ? null : value);
  }

  function cacheKeyFor(query, variables) {
    return query + '|' + stableStringify(variables || {});
  }

  function emptyStore() {
    return {
      v: STORAGE_VERSION,
      plugin: ACFFA.plugin_version || '',
      kit: ACFFA.kit_token || '',
      revision: null,
      entries: {},
    };
  }

  function loadPersistentStore() {
    if (!ACFFA.kit_token) {
      return emptyStore();
    }
    try {
      const raw = window.localStorage.getItem(STORAGE_KEY);
      const store = raw ? JSON.parse(raw) : null;
      if (
        store &&
        store.v === STORAGE_VERSION &&
        store.plugin === (ACFFA.plugin_version || '') &&
        store.kit === ACFFA.kit_token &&
        store.entries &&
        typeof store.entries === 'object'
      ) {
        return store;
      }
    } catch (e) {
      // localStorage unavailable or corrupt, start fresh.
    }
    return emptyStore();
  }

  function savePersistentStore() {
    if (!ACFFA.kit_token) {
      return;
    }
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(persistentStore));
    } catch (e) {
      // Quota exceeded or storage disabled: the in-memory cache still works.
    }
  }

  function rememberInMemory(key, data) {
    if (memoryCache.has(key)) {
      memoryCache.delete(key);
    }
    memoryCache.set(key, data);
    if (memoryCache.size > MEMORY_CACHE_LIMIT) {
      memoryCache.delete(memoryCache.keys().next().value);
    }
  }

  // Called with every fresh KitRevision response. When the kit changed, all
  // cached kit data (and search results, which depend on the kit) is dropped.
  function applyKitRevision(payload) {
    const kit = (payload && payload.data && payload.data.me && payload.data.me.kit) || {};
    const revision = String(kit.kitRevision ?? '') + '|' + String(kit.showcaseCacheKey ?? '');

    if (persistentStore.revision !== revision) {
      persistentStore = emptyStore();
      persistentStore.revision = revision;
      memoryCache.clear();
      savePersistentStore();
    }
  }

  // Sends a single query to the server (admin-ajax -> api.fontawesome.com).
  async function sendQuery(query, variables) {
    const formData = new FormData();
    formData.append('action', 'acffa_fa_query');
    formData.append('query', query);
    formData.append('variables', JSON.stringify(variables || {}));
    formData.append('nonce', ACFFA.nonce);

    const res = await fetch(ACFFA.ajax_url, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    });

    if (!res.ok) {
      throw new Error('Font Awesome API request failed');
    }

    const data = await res.json();

    if (!data.success) {
      throw new Error('Font Awesome API request was not successful');
    }

    const payload = data.data || {};
    if (payload.errors) {
      console.error('Font Awesome API errors:', payload.errors);
      throw new Error('Font Awesome API returned errors');
    }

    return payload;
  }

  // Shares identical in-flight requests and stores successful results.
  function cachedQuery(query, variables, persist) {
    const key = cacheKeyFor(query, variables);

    if (memoryCache.has(key)) {
      return Promise.resolve(memoryCache.get(key));
    }

    if (persist && persistentStore.revision && persistentStore.entries.hasOwnProperty(key)) {
      const data = persistentStore.entries[key];
      rememberInMemory(key, data);
      return Promise.resolve(data);
    }

    if (inflightQueries.has(key)) {
      return inflightQueries.get(key);
    }

    const revisionAtStart = persistentStore.revision;
    const promise = sendQuery(query, variables)
      .then(payload => {
        // Don't store a response that was requested before the kit changed.
        if (persistentStore.revision === revisionAtStart) {
          rememberInMemory(key, payload);
          if (persist && persistentStore.revision) {
            persistentStore.entries[key] = payload;
            savePersistentStore();
          }
        }
        return payload;
      })
      .finally(() => {
        inflightQueries.delete(key);
      });

    inflightQueries.set(key, promise);
    return promise;
  }

  // The KitRevision probe decides whether cached kit data is still valid.
  // It is re-used for a short while instead of being repeated on every open.
  function kitRevisionQuery(query, variables) {
    const now = Date.now();
    if (revisionPromise && now - revisionFetchedAt < REVISION_TTL) {
      return revisionPromise;
    }

    revisionFetchedAt = now;
    revisionPromise = sendQuery(query, variables).then(
      payload => {
        applyKitRevision(payload);
        return payload;
      },
      error => {
        revisionPromise = null;
        throw error;
      },
    );

    return revisionPromise;
  }

  async function handleQuery(query, variables, options) {
    const cleanedQuery = normalizeQuery(query);
    const operation = operationName(cleanedQuery);
    const persist = !!(options && options.cache === true);

    if ('KitRevision' === operation) {
      return kitRevisionQuery(cleanedQuery, variables);
    }

    // searchKit is paged by 50; broad searches need a second page, which the
    // component only requests after the first one returned. Request it in
    // parallel so it doesn't cost another full round trip.
    if ('SearchKit' === operation && variables && variables.page === 1) {
      cachedQuery(cleanedQuery, { ...variables, page: 2 }, false).catch(() => {});
    }

    return cachedQuery(cleanedQuery, variables, persist);
  }

  // Warms the cache in the background so the first open of the picker
  // doesn't have to wait for the kit data either.
  async function prefetchKitData() {
    const token = ACFFA.kit_token;
    if (!token || !ACFFA.has_api_token) {
      return;
    }

    await kitRevisionQuery(QUERY_KIT_REVISION, { token });
    const metadata = await cachedQuery(QUERY_KIT_METADATA, { token }, true);

    // Prefetch the showcase for the family-style the picker opens with
    // (Classic Solid when available, otherwise the first one).
    const familyStyles = (
      (metadata &&
        metadata.data &&
        metadata.data.me &&
        metadata.data.me.kit &&
        metadata.data.me.kit.familyStylesPaginated &&
        metadata.data.me.kit.familyStylesPaginated.familyStyles) ||
      []
    )
      .map(entry => entry && entry.familyStyle)
      .filter(fs => fs && fs.prefix);

    if (familyStyles.length === 0) {
      return;
    }

    let initial = familyStyles.find(fs => fs.family === 'classic' && fs.style === 'solid');
    if (!initial) {
      initial = familyStyles.slice().sort((a, b) =>
        a.family === b.family ? a.style.localeCompare(b.style) : a.family.localeCompare(b.family),
      )[0];
    }

    await cachedQuery(QUERY_SHOWCASE_ICONS, { token, selector: { prefix: initial.prefix } }, true);
  }

  function schedulePrefetch() {
    if (!document.querySelector('[data-type="font-awesome"], .fa-icon-chooser-open')) {
      return;
    }
    const run = () => prefetchKitData().catch(() => {});
    if ('requestIdleCallback' in window) {
      window.requestIdleCallback(run, { timeout: 3000 });
    } else {
      setTimeout(run, 500);
    }
  }

  if (document.readyState === 'complete') {
    schedulePrefetch();
  } else {
    window.addEventListener('load', schedulePrefetch, { once: true });
  }

  // Used for the Font Awesome JS API and (Pro) icon SVG data. Identical
  // requests are shared and successful responses are kept for the page.
  function getUrlText(url) {
    if (inflightUrls.has(url)) {
      return inflightUrls.get(url);
    }

    const promise = fetch(url).then(response => {
      if (response.ok) {
        return response.text();
      } else {
        throw new Error('Bad query for url: ' + url);
      }
    });

    inflightUrls.set(url, promise);
    promise.catch(() => inflightUrls.delete(url));

    return promise;
  }

  function includeFamilyStyle(familyStyle) {
    if (iconSets.length === 0) {
      return true;
    }

    let familyStyleString = familyStyle['family'] + '_' + familyStyle['style'];

    return iconSets.includes(familyStyleString);
  }

  function handleResult(event) {
    const result = event.detail || {};

    let iconData = {
      family: result.family || '',
      style: result.style || '',
      id: result.iconName || '',
      unicode: result.icon[3] || '',
    };

    inputEl.val(`${JSON.stringify(iconData)}`).trigger('change');

    previewEl.html('');
    if (iconData.style && iconData.id) {
      const i = getPreviewElement(iconData);
      previewEl.append(i);
    }

    // Remove chooser when done (optional)
    const chooser = container.find('fa-icon-chooser');
    if (chooser.length) chooser.remove();

    container.removeClass('open');
  }

  function getPreviewElement(iconData) {
    if (!iconData.style || !iconData.id) {
      return null;
    }
    const i = document.createElement('i');
    let classes = [];
    classes.push('fa-' + iconData.family);
    classes.push('fa-' + iconData.style);
    classes.push('fa-' + iconData.id);
    i.className = classes.join(' ');
    return i;
  }

  function openIconChooser() {
    // Remove existing chooser
    let existing = container.find('fa-icon-chooser');
    if (existing.length) existing.remove();

    const el = document.createElement('fa-icon-chooser');

    // Attach required callbacks as properties
    el.handleQuery = handleQuery;
    el.getUrlText = getUrlText;
    el.includeFamilyStyle = includeFamilyStyle;

    if (
      (!ACFFA.kit_token && ACFFA.latest_version) ||
      (!ACFFA.has_api_token && ACFFA.latest_version)
    ) {
      el.setAttribute('version', ACFFA.latest_version);
    } else if (ACFFA.kit_token) {
      el.setAttribute('kit-token', ACFFA.kit_token);
    }

    el.addEventListener('finish', handleResult);

    // create empty slot start-view-heading and start-view-detail
    const startViewHeading = document.createElement('div');
    startViewHeading.setAttribute('slot', 'start-view-heading');
    startViewHeading.textContent = '';
    el.appendChild(startViewHeading);
    const startViewDetails = document.createElement('div');
    startViewDetails.setAttribute('slot', 'start-view-detail');
    startViewDetails.textContent = '';
    el.appendChild(startViewDetails);

    // Optional slot content
    const fatalErrorHeading = document.createElement('p');
    fatalErrorHeading.setAttribute('slot', 'fatal-error-heading');
    fatalErrorHeading.textContent = 'Something went wrong';
    el.appendChild(fatalErrorHeading);

    container.find('.chooser-wrapper').append(el);

    container.addClass('open');
  }

  function insertIconChooserContainer() {
    const containerHtml = `
		<div id="acffa-icon-chooser-container">
        <div class="acffa-icon-chooser-card">
			<div class="title-wrapper">
				<h2>Choose an Icon</h2>
				<button type="button" class="acffa-icon-chooser-close button-link"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.1.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M183.1 137.4C170.6 124.9 150.3 124.9 137.8 137.4C125.3 149.9 125.3 170.2 137.8 182.7L275.2 320L137.9 457.4C125.4 469.9 125.4 490.2 137.9 502.7C150.4 515.2 170.7 515.2 183.2 502.7L320.5 365.3L457.9 502.6C470.4 515.1 490.7 515.1 503.2 502.6C515.7 490.1 515.7 469.8 503.2 457.3L365.8 320L503.1 182.6C515.6 170.1 515.6 149.8 503.1 137.3C490.6 124.8 470.3 124.8 457.8 137.3L320.5 274.7L183.1 137.4z"/></svg></button>
			</div>
			<div class="chooser-wrapper"></div>
        </div>
		</div>
    `;
    $('body').append(containerHtml);

    container = $('#acffa-icon-chooser-container');
  }
  insertIconChooserContainer();

  // when clicking outside the card or on the close button, close the chooser
  container.on('click', function (e) {
    if ($(e.target).is('#acffa-icon-chooser-container')) {
      container.removeClass('open');
      container.find('fa-icon-chooser').remove();
    }

    if ($(e.target).closest('.acffa-icon-chooser-close').length) {
      container.removeClass('open');
      container.find('fa-icon-chooser').remove();
    }
  });

  function setupFieldActions($el) {
    $el.find('.fa-icon-chooser-open').on('click', function () {
      let wrapper = $(this).closest('.acf-field');
      let iconSetsInput = wrapper.find('input[name="icon_sets"]');

      iconSets = iconSetsInput.val() ? iconSetsInput.val().split(',') : [];
      previewEl = wrapper.find('.icon_preview');
      inputEl = wrapper.find('.acf-input-wrap input');

      openIconChooser();
    });

    $el.find('.fa-icon-clear').on('click', function () {
      let wrapper = $(this).closest('.acf-input');
      let input = wrapper.find('input');
      let preview = wrapper.find('.icon_preview');
      input.val('');
      preview.empty();
    });
  }

  function setupEditFieldActions($el) {
    let defaultValueWrapper = $el.find('.acf-field-setting-default_value');

    // if has class acffa-initialized, skip
    if (defaultValueWrapper.hasClass('acffa-initialized')) {
      return;
    }
    defaultValueWrapper.addClass('acffa-initialized');

    let inputWrapper = defaultValueWrapper.find('.acf-input');
    inputWrapper.prepend(
      `<div class="icon_preview"></div><button type="button" class="fa-icon-chooser-open button">Choose icon</button>
      			<button type="button" class="fa-icon-clear" title="Clear icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.-->
						<path d="M183.1 137.4C170.6 124.9 150.3 124.9 137.8 137.4C125.3 149.9 125.3 170.2 137.8 182.7L275.2 320L137.9 457.4C125.4 469.9 125.4 490.2 137.9 502.7C150.4 515.2 170.7 515.2 183.2 502.7L320.5 365.3L457.9 502.6C470.4 515.1 490.7 515.1 503.2 502.6C515.7 490.1 515.7 469.8 503.2 457.3L365.8 320L503.1 182.6C515.6 170.1 515.6 149.8 503.1 137.3C490.6 124.8 470.3 124.8 457.8 137.3L320.5 274.7L183.1 137.4z" />
					</svg>
				</button>`,
    );

    // Initialize preview based on existing value
    let input = inputWrapper.find('input');
    try {
      const iconData = input.val() ? JSON.parse(input.val()) : {};
      const preview = inputWrapper.find('.icon_preview');
      if (iconData.style && iconData.id) {
        preview.append(getPreviewElement(iconData));
      }
    } catch (e) {
      // Invalid JSON, skip preview
    }

    $el.find('.fa-icon-chooser-open').on('click', function () {
      let wrapper = $(this).closest('.acf-input');

      iconSets = [];
      previewEl = wrapper.find('.icon_preview');
      inputEl = wrapper.find('input');

      openIconChooser();
    });

    $el.find('.fa-icon-clear').on('click', function () {
      let wrapper = $(this).closest('.acf-input');
      let input = wrapper.find('input');
      let preview = wrapper.find('.icon_preview');
      input.val('').trigger('change');
      preview.empty();
    });
  }

  acf.add_action(
    'ready_field/type=font-awesome append_field/type=font-awesome show_field/type=font-awesome new_field/type=font-awesome',
    function ($el) {
      setupFieldActions($el);
    },
  );

  acf.add_action(
    'open_field/type=font-awesome change_field_type/type=font-awesome',
    function ($el) {
      setupEditFieldActions($el);
    },
  );
})(jQuery);
