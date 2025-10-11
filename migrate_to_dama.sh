#!/bin/bash
echo "🔧 Migration des tests vers DAMA"
find api/tests -name "*Test.php" -type f | while read file; do
    if grep -q "use.*ResetDatabase" "$file"; then
        echo "📝 $file"
        sed -i '/use Zenstruck\\Foundry\\Test\\ResetDatabase;/d' "$file"
        sed -i 's/use ResetDatabase, Factories;/use Factories;/g' "$file"
        sed -i 's/use Factories, ResetDatabase;/use Factories;/g' "$file"
        sed -i '/^\s*use ResetDatabase;/d' "$file"
    fi
done
echo "✅ Terminé!"
