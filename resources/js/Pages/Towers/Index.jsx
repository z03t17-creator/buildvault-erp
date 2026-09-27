import PageStub from "@/Components/PageStub";
import { Link } from "@inertiajs/react";

export default function Index({ project, towers }) {
  return (
    <PageStub title={`${project.name} · Towers`} links={[
      { href: route("projects.show", project.id), label: "Project" },
      { href: route("projects.towers.create", project.id), label: "Add tower" },
    ]}>
      <ul className="space-y-2">
        {(towers || []).map((t) => (
          <li key={t.id}><Link href={route("towers.show", t.id)} className="underline text-emerald-700 dark:text-emerald-400">{t.name}</Link></li>
        ))}
      </ul>
    </PageStub>
  );
}
