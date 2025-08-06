define("group-mention:views/stream/notes/post", ["views/stream/notes/post"], function (
    Dep
) {
    return Dep.extend({
        setup: function () {
            console.log("group-mention:views/stream/notes/post");
            Dep.prototype.setup.call(this);
            this.createField('post', null, null, 'group-mention:views/stream/fields/post');
        }
    });
});
