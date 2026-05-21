class UserProfile extends HTMLElement {
    connectedCallback() {
        const template = document.getElementById('user-profile-template');
        if (template) {
            const content = template.content.cloneNode(true);
            this.innerHTML = '';
            this.appendChild(content);
        }

        if (typeof htmx !== 'undefined') {
            htmx.process(this);
        }
    }
}

customElements.define('user-profile', UserProfile);
