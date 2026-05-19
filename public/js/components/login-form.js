class LoginForm extends HTMLElement {
  connectedCallback() {
      const template = document.getElementById('login-form-template');
      const content = template.content.cloneNode(true);
      const form = content.querySelector('form');
      const action = this.getAttribute('action') || '/auth/login';
      const target = this.getAttribute('target') || 'find #error-message';

      form.setAttribute('hx-post', action);
      form.setAttribute('hx-target', target);
      form.setAttribute('hx-swap', 'innerHTML settle:0s swap:400');
      this.innerHTML = '';
      this.appendChild(content);

      // Critical for HTMX to recognize the dynamically added hx-* attributes
      if (typeof htmx !== 'undefined') {
        htmx.process(this);
      }
    }
}

customElements.define('login-form', LoginForm);
