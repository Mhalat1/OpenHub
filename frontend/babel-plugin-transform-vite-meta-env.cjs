module.exports = function () {
  return {
    name: "transform-vite-meta-env",
    visitor: {
      MemberExpression(path) {
        // Cas 1 : import.meta.env → process.env
        if (
          path.matchesPattern("import.meta.env") ||
          (path.get("object").matchesPattern("import.meta") &&
            path.get("property").isIdentifier({ name: "env" }))
        ) {
          path.replaceWithSourceString("process.env");
        }

        // Cas 2 : import.meta.env.VITE_API_URL → process.env.VITE_API_URL
        if (path.matchesPattern("import.meta.env.*")) {
          const propertyName = path.node.property.name;
          path.replaceWithSourceString(`process.env.${propertyName}`);
        }
      },
    },
  };
};