export default {
  title: 'Components/UserProfile',
};

export const LiveDatabaseState = {
  parameters: {
    server: { id: 'partials/user-profile.html.twig' },
  },
  args: {
    source_url: 'http://localhost:8000/api/profile-card',
  },
};

export const MockedProfileState = {
  parameters: {
    server: { id: 'partials/user-profile.html.twig' },
  },
  args: {
    render_content_only: true,
    display_name: 'Russell Warner',
    email: 'russell@example.com',
    website: 'https://github.com/russellbits',
    bio: 'Front-end engineer building decentralized local web infrastructures.',
  },
};
