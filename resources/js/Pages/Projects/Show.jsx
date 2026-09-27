import PageStub from "@/Components/PageStub";
import { Link } from "@inertiajs/react";

export default function Show({ project }) {
  return (
    <PageStub
      title={project.name}
      links={[
        { href: route("projects.index"), label: "Projects" },
        { href: route("projects.edit", project.id), label: "Edit" },
        { href: route("projects.towers.index", project.id), label: "Towers" },
        { href: route("projects.towers.create", project.id), label: "Add tower" },
      ]}
    >
      <p className="mb-2">{project.description || "No description."}</p>
      <p className="text-slate-500">Status: {project.status} · Location: {project.location || "—"}</p>
      <h3 className="mt-4 font-semibold">Towers</h3>
      <ul className="mt-2 space-y-1">
        {(project.towers || []).map((t) => (
          <li key={t.id}><Link href={route("towers.show", t.id)} className="underline text-emerald-700 dark:text-emerald-400">{t.name}</Link></li>
        ))}
      </ul>
    </PageStub>
  );
}
