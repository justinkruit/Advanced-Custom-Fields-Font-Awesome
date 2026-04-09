import 'https://cdn.jsdelivr.net/npm/@fortawesome/fa-icon-chooser@0.10.0-2/dist/fa-icon-chooser/fa-icon-chooser.esm.js';

(function ($) {
  // Font Awesome API endpoint for GraphQL
  const API_URL = 'https://api.fontawesome.com';

  let iconSets = [];
  let previewEl = null;
  let inputEl = null;
  let container = null;

  function getUrlText(url) {
    return fetch(url).then(response => {
      if (response.ok) {
        return response.text();
      } else {
        throw new Error('Bad query for url: ' + url);
      }
    });
  }

  async function handleQuery(query, variables) {
    const cleanedQuery = query.replace(/\s+/g, ' ');

    const formData = new FormData();
    formData.append('action', 'acffa_fa_query');
    formData.append('query', cleanedQuery);
    formData.append('variables', JSON.stringify(variables || {}));
    formData.append('nonce', ACFFA.nonce);

    const res = await fetch(ACFFA.ajax_url, {
      method: 'POST',
      body: formData,
    });

    if (!res.ok) {
      throw new Error('Font Awesome API request failed');
    }

    const data = await res.json();
    if (data.errors) throw new Error('Font Awesome API returned errors');
    return data.data;
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

    if (!ACFFA.kit_token && ACFFA.latest_version) {
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
      '<div class="icon_preview"></div><button type="button" class="fa-icon-chooser-open button">Choose icon</button>'
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
  }

  acf.add_action(
    'ready_field/type=font-awesome append_field/type=font-awesome show_field/type=font-awesome new_field/type=font-awesome',
    function ($el) {
      setupFieldActions($el);
    }
  );

  acf.add_action(
    'open_field/type=font-awesome change_field_type/type=font-awesome',
    function ($el) {
      setupEditFieldActions($el);
    }
  );
})(jQuery);
