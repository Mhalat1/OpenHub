#!/bin/bash

echo "í´§ Correction de tous les fichiers de test..."

# 1. Sauvegarde de tous les fichiers
mkdir -p test-backup
cp src/JEST/*.test.jsx test-backup/

# 2. Corrige getByText avec [0] dans tous les fichiers
for file in src/JEST/*.test.jsx; do
  echo "Correction de $(basename $file)..."
  
  # Remplace getByText(...)[0] par getByText(...)
  sed -i 's/\(screen\.getByText\s*([^)]*)\)\s*\[\s*0\s*\]\.toBeInTheDocument/\1.toBeInTheDocument/g' "$file"
  
  # Remplace aussi les cas avec des espaces et guillemets diffÃ©rents
  sed -i 's/\(screen\.getByText\s*([^)]*)\)\[0\]\.toBeInTheDocument/\1.toBeInTheDocument/g' "$file"
done

# 3. Correction spÃ©ciale pour Register.test.jsx (multiples erreurs)
echo "Correction spÃ©ciale pour Register.test.jsx..."
cat > register-fix.js << 'REG_EOF'
const fs = require('fs');
const path = require('path');

const filePath = path.join(__dirname, 'src/JEST/Register.test.jsx');
let content = fs.readFileSync(filePath, 'utf8');

// Remplacer les getByText pour les messages d'erreur multiples
content = content.replace(
  /expect\(screen\.getByText\(["']Champ requis["']\)\)\.toBeInTheDocument\(\)/g,
  'const errors = screen.getAllByText("Champ requis");\n  expect(errors.length).toBeGreaterThan(0);'
);

// Corriger les getByText avec index
content = content.replace(
  /screen\.getByText\(([^)]*)\)\[0\]/g,
  'screen.getByText($1)'
);

fs.writeFileSync(filePath, content);
console.log('âœ… Register.test.jsx corrigÃ©');
REG_EOF

node register-fix.js
rm register-fix.js

# 4. Commente tous les console.log dans les tests
for file in src/JEST/*.test.jsx; do
  sed -i 's/^\(\s*\)console\.log/\1\/\/ console.log/g' "$file"
done

# 5. Ajoute les variables d'environnement manquantes
for file in src/JEST/Messages.test.jsx src/JEST/Home.test.jsx; do
  if ! grep -q "VITE_API_URL" "$file"; then
    sed -i '1s/^/process.env.VITE_API_URL = "http:\/\/localhost:3000";\n/' "$file"
  fi
done

echo "âœ… Toutes les corrections sont terminÃ©es !"
echo "í³ Les sauvegardes sont dans test-backup/"
