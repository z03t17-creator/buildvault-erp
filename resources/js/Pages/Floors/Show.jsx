import PageStub from "@/Components/PageStub";

export default function Show({ floor, tower, project }) {
  return (
    <PageStub title={floor.name} links={[
      { href: route("towers.show", tower.id), label: "Tower" },
      { href: route("floors.edit", floor.id), label: "Edit" },
    ]}>
      <p>Project: {project.name}</p>
    </PageStub>
  );
}
