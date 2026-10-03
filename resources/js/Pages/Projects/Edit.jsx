import ProjectForm from '@/Pages/Projects/ProjectForm';

export default function Edit({ project }) {
    return <ProjectForm mode="edit" project={project} />;
}
