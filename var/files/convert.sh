#/bin/bash

for dir in {armours,modules,weapons}
do
  echo "Traitement du dossier $dir..."

  cp $dir/png/*.png $dir/

  for i in $dir/*.png; do convert -resize 30% $i $i; done

  for i in $dir/*.png; do cwebp -quiet -q 70 $i -o ${i/png/webp}; done

  rm -rf $dir/*.png

  mv $dir/*.webp $dir/webp
done
