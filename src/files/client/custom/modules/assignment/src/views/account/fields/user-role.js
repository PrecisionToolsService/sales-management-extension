define("modules/assignment/views/account/fields/user-role", [
    "views/fields/enum",
], function (Dep) {
    return Dep.extend({
        searchTypeList: ["anyOf", "noneOf"],
    });
});
