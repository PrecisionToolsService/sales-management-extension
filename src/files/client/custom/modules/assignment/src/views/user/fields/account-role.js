define("modules/assignment/views/user/fields/account-role", [
    "views/fields/enum",
], function (Dep) {
    return Dep.extend({
        searchTypeList: ["anyOf", "noneOf"],
    });
});
