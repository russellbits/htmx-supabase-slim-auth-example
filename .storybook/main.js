export default {
  stories: ["../stories/**/*.stories.@(json|js|mjs|ts)"],
  addons: ["@storybook/addon-essentials"],
  framework: {
    name: "@storybook/html-vite",
    options: {},
  },
};
