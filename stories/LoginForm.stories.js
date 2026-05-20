export default {
  title: 'Components/LoginForm',
};

export const DefaultState = {
  parameters: {
    server: { id: 'partials/login-form.html.twig' },
  },
  args: {
    action: '/auth/login',
    target: 'find #error-message',
  },
};

export const CustomEndpointState = {
  parameters: {
    server: { id: 'partials/login-form.twig' },
  },
  args: {
    action: '/api/v1/mock-login',
    target: 'find #error-message',
  },
};
