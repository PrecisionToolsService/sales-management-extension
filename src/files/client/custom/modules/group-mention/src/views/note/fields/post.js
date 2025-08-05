define("group-mention:views/note/fields/post", [
    "views/note/fields/post",
], function (Dep) {
    return Dep.extend({
        setup: function () {
            console.log("setup");
            Dep.prototype.setup.call(this);
        },
        afterRenderEdit: function () {
            console.log("afterRenderEdit");
            Dep.prototype.afterRenderEdit.call(this);
        },
        initMentions() {
            console.log("initMentions");
            const mentionPermissionLevel =
                this.getAcl().getPermissionLevel("mention");
            if (mentionPermissionLevel === "no" /*|| this.model.isNew()*/) {
                return;
            }
            const maxSize = this.getConfig().get("recordsPerPage");
            const buildUserListUrl = (term) => {
                let url =
                    `User?` +
                    `${$.param({
                        q: term,
                    })}` +
                    `&${$.param({
                        primaryFilter: "active",
                    })}` +
                    `&orderBy=name` +
                    `&maxSize=${maxSize}` +
                    `&select=id,name,userName`;
                if (mentionPermissionLevel === "team") {
                    url +=
                        "&" +
                        $.param({
                            boolFilterList: ["onlyMyTeam"],
                        });
                }
                return url;
            };

            // noinspection JSUnresolvedReference
            this.$element.textcomplete(
                [
                    {
                        match: /(^|\s)@(\w[\w@.-]*)$/,
                        index: 2,
                        // @todo Revise.
                        search: (term, callback) => {
                            if (term.length === 0) {
                                callback([]);
                                return;
                            }
                            Espo.Ajax.getRequest(buildUserListUrl(term)).then(
                                (data) => callback(data.list)
                            );
                        },
                        template: (mention) => {
                            return (
                                this.getHelper().getAvatarHtml(
                                    mention.id,
                                    "medium",
                                    16,
                                    "avatar-link"
                                ) +
                                this.getHelper().escapeString(mention.name) +
                                ' <span class="text-muted">@' +
                                this.getHelper().escapeString(
                                    mention.userName
                                ) +
                                "</span>"
                            );
                        },
                        replace: (o) => {
                            return "$1@" + o.userName + "";
                        },
                    },
                ],
                {
                    zIndex: 1100,
                }
            );
            this.on("remove", () => {
                if (this.$element.length) {
                    this.$element.textcomplete("destroy");
                }
            });
        },
    });
});
