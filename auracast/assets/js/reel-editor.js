(() => {
  'use strict';
  document.querySelectorAll('[data-reel-media-field]').forEach(field => {
    const input = field.querySelector('input[type="hidden"]'), preview = field.querySelector('.auracast-reel-preview'), picker = field.querySelector('[data-reel-pick]');
    let frame;
    picker.addEventListener('click', () => {
      if (!frame) {
        const type = picker.dataset.reelPick;
        frame = wp.media({title: type === 'video' ? 'Choose a reel video' : 'Choose a cover image', button: {text: 'Use this media'}, library: {type}, multiple: false});
        frame.on('select', () => {
          const attachment = frame.state().get('selection').first().toJSON(); input.value = attachment.id; preview.replaceChildren();
          const media = document.createElement(type === 'video' ? 'video' : 'img'); media.src = attachment.url;
          if (type === 'video') { media.controls = true; media.preload = 'metadata'; } else media.alt = 'Selected cover';
          preview.append(media);
        });
      }
      frame.open();
    });
    field.querySelector('[data-reel-remove]').addEventListener('click', () => { preview.querySelector('video')?.pause(); input.value = ''; preview.replaceChildren(); });
  });
})();
