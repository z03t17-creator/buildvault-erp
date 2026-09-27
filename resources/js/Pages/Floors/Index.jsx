import PageStub from "@/Components/PageStub";
import { Link } from "@inertiajs/react";

export default function Index({ tower, project, floors }) {
  return (
    <PageStub title={`${tower.name} · Floors`} links={[
      { href: route("towers.show", tower.id), label: "Tower" },
      { href: route("towers.floors.create", tower.id), label: "Add floor" },
    ]}>
      <ul className="space-y-2">
        {(floors || []).map((f) => (
          <li key={f.id}><Link href={route("floors.show", f.id)} className="underline text-emerald-700 dark:text-emerald-400">{f.name}</Link></li>
        ))}
      </ul>
    </PageStub>
  );
}
