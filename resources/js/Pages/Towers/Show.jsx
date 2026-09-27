import PageStub from "@/Components/PageStub";
import { Link } from "@inertiajs/react";

export default function Show({ tower, project }) {
  return (
    <PageStub title={tower.name} links={[
      { href: route("projects.show", project.id), label: "Project" },
      { href: route("towers.edit", tower.id), label: "Edit" },
      { href: route("towers.floors.index", tower.id), label: "Floors" },
      { href: route("towers.floors.create", tower.id), label: "Add floor" },
    ]}>
      <ul className="space-y-1">
        {(tower.floors || []).map((f) => (
          <li key={f.id}><Link href={route("floors.show", f.id)} className="underline text-emerald-700 dark:text-emerald-400">{f.name}</Link></li>
        ))}
      </ul>
    </PageStub>
  );
}
