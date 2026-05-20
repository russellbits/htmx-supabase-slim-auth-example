/** @type { import('@storybook/html').Preview } */
const preview = {
  parameters: {
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
        },
      },
    },

  // This forces Storybook to use our fetch pipeline globally across ALL stories
  render: (args, { parameters }) => {
    const componentId = parameters.server?.id;
    if (!componentId) return '<div>Error: Missing server template ID parameter.</div>';

    const url = new URL('http://localhost:8000/storybook/render');
    url.searchParams.append('id', componentId);
    url.searchParams.append('args', JSON.stringify(args));

    const container = document.createElement('div');

    fetch(url)
      .then(res => {
        if (!res.ok) throw new Error(`Server returned status ${res.status}`);
        return res.text();
      })
      .then(html => {
        container.innerHTML = html;
      })
      .catch(err => {
        container.innerHTML = `<div style="color: red; font-family: monospace; padding: 10px;">
          <strong>Fetch Error:</strong> ${err.message}<br>
          <small>Make sure your Slim server is running at localhost:8000</small>
        </div>`;
      });

    return container;
  },
};

export default preview;
