import ProjectForm from '@/Pages/Projects/ProjectForm';

export default function Edit({ project, statuses }) {
    return <ProjectForm mode="edit" project={project} statuses={statuses} />;
}
