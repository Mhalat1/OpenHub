module.exports = function () {
  return {
    name: "transform-vite-meta-env",
    visitor: {
      MemberExpression(path) {
        if (
          path.matchesPattern("import.meta.env") ||
          (path.get("object").matchesPattern("import.meta") &&
            path.get("property").isIdentifier({ name: "env" }))
        ) {
          path.replaceWithSourceString("process.env");
        }
        if (path.matchesPattern("import.meta.env.*")) {
          const propertyName = path.node.property.name;
          path.replaceWithSourceString(`process.env.${propertyName}`);
        }
      },
      MetaProperty(path) {
        if (
          path.node.meta.name === "import" &&
          path.node.property.name === "meta"
        ) {
          const parent = path.parent;
          if (
            parent.type === "MemberExpression" &&
            parent.property.name === "env"
          ) {
            return;
          }
        }
      },
    },
  };
};