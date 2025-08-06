define("group-mention:views/stream/fields/post", ["views/stream/fields/post"], function (
    Dep
) {
    return Dep.extend({
        getTransformedValue() {
            console.log("group-mention:views/stream/fields/post");
            let text = Dep.prototype.getValueForDisplay.call(this);;

            if (typeof text !== 'string' && !(text instanceof String)) {
                return '';
            }

            /** @type {Record} */
            const data = this.model.attributes.data || {}

            const mentionData = /** @type {Record.<string, {id: string, name: string}>} */
                data.mentions || {};

            const items = Object.keys(mentionData).sort((a, b) => b.length - a.length);

            if (!items.length) {
                return this.getHelper().transformMarkdownText(text);
            }

            items.forEach(item => {
                const name = mentionData[item].name;
                const id = mentionData[item].id;
                const scope = mentionData[item]._scope;

                const part = `[${name}](#${scope}/view/${id})`;

                text = text.replace(new RegExp(item, 'g'), part);
            });

            let html = this.getHelper().transformMarkdownText(text).toString();

            const body = new DOMParser().parseFromString(html, 'text/html').body;

            items.forEach(item => {
                const id = mentionData[item].id;
                const scope = mentionData[item]._scope;
                const url = `#${scope}/view/${id}`;

                const avatarHtml = this.getHelper().getAvatarHtml(id, 'small', 16, 'avatar-link');

                if (!avatarHtml) {
                    return;
                }

                const img = new DOMParser().parseFromString(avatarHtml, 'text/html').body.childNodes[0];

                body.querySelectorAll(`a[href="${url}"]`).forEach(a => {
                    if (id === this.getUser().id) {
                        a.classList.add('text-warning');
                    }

                    const span = document.createElement('span');
                    span.classList.add('nowrap', 'name-avatar');

                    if (scope == "User") span.append(img.cloneNode());

                    a.parentNode.replaceChild(span, a);

                    span.append(a);
                });
            });

            html = body.innerHTML;

            return html;
        }
    });
});
