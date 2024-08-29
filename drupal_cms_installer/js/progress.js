(function (Drupal) {

  /**
   * Renders a client-side progress bar as a custom throbber.
   *
   * This is for the Drupal CMS installer and is not meant to be reused.
   */
  Drupal.theme.progressBar = function (id) {
    const escapedId = Drupal.checkPlain(id);
    return (
      `<div id="${escapedId}" class="progress" aria-live="polite">` +
      `<svg xmlns="http://www.w3.org/2000/svg"  version="1.0" width="160px" height="160px" viewBox="0 0 128 128" xml:space="preserve">
        <circle cx="64.13" cy="64.13" r="27.63" fill="#000000"/>
        <path d="M64.13 18.5A45.63 45.63 0 1 1 18.5 64.13 45.63 45.63 0 0 1 64.13 18.5zm0 7.85a37.78 37.78 0 1 1-37.78 37.78 37.78 37.78 0 0 1 37.78-37.78z" fill-rule="evenodd" fill="#000000"/>
        <g>
          <path d="M95.25 17.4a56.26 56.26 0 0 0-76.8 13.23L12.1 26.2a64 64 0 0 1 87.6-15.17z" fill="#000000"/>
          <path d="M95.25 17.4a56.26 56.26 0 0 0-76.8 13.23L12.1 26.2a64 64 0 0 1 87.6-15.17z" fill="#000000" transform="rotate(120 64 64)"/>
          <path d="M95.25 17.4a56.26 56.26 0 0 0-76.8 13.23L12.1 26.2a64 64 0 0 1 87.6-15.17z" fill="#000000" transform="rotate(240 64 64)"/>
          <animateTransform attributeName="transform" type="rotate" from="0 64 64" to="120 64 64" dur="480ms" repeatCount="indefinite"></animateTransform>
        </g>
      </svg>` +
      // '<div class="progress__label">&nbsp;</div>' +
      // '<div class="progress__track"><div class="progress__bar"></div></div>' +
      '<div class="progress__percentage"></div>' +
      // '<div class="progress__description">&nbsp;</div>' +
      '</div>'
    );
  };

})(Drupal);
