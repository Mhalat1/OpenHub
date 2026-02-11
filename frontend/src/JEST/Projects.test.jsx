import React from 'react';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import Projects from '../pages/Projects';

// Mock de l'API URL
jest.mock('../pages/Projects', () => {
  const actual = jest.requireActual('../pages/Projects');
  return {
    ...actual,
    __esModule: true,
    default: actual.default,
  };
});

// Configuration des variables d'environnement
const API_URL = 'http://localhost:3000';
process.env.VITE_API_URL = API_URL;

// Mock pour fetch
global.fetch = jest.fn();

// Mock pour localStorage
const localStorageMock = {
  getItem: jest.fn(() => 'mock-token'),
  setItem: jest.fn(),
  clear: jest.fn(),
  removeItem: jest.fn(),
};
Object.defineProperty(window, 'localStorage', { value: localStorageMock });

// Mock pour window.confirm
window.confirm = jest.fn(() => true);

describe('Projects Component', () => {
  beforeEach(() => {
    fetch.mockClear();
    localStorageMock.getItem.mockClear();
    window.confirm.mockClear();
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  const mockProjects = [
    {
      id: 1,
      name: 'Project Alpha',
      description: 'A cutting-edge web application',
      requiredSkills: 'React, Node.js, MongoDB',
      startDate: '2024-01-01T00:00:00.000Z',
      endDate: '2024-06-30T00:00:00.000Z'
    },
    {
      id: 2,
      name: 'Project Beta',
      description: 'Mobile app development',
      requiredSkills: 'React Native, Firebase',
      startDate: '2024-02-01T00:00:00.000Z',
      endDate: '2024-08-31T00:00:00.000Z'
    }
  ];

  const mockSkills = [
    {
      id: 1,
      name: 'Frontend Development',
      description: 'Building responsive user interfaces',
      technoUtilisees: 'React, Vue, Angular',
      duree: '3 months'
    },
    {
      id: 2,
      name: 'Backend Development',
      description: 'Server-side programming',
      technoUtilisees: 'Node.js, Express, MongoDB',
      duree: '4 months'
    }
  ];

  // Test 1: Initial loading state
  test('1. Displays loading state initially', () => {
    fetch.mockImplementation(() => new Promise(() => {})); // Never resolves

    render(<Projects />);

    expect(screen.getByText(/loading projects and skills/i)).toBeInTheDocument();
    expect(document.querySelector('.spinner')).toBeInTheDocument();
  });

  // Test 2: Renders projects and skills after loading
  test('2. Renders projects and skills after successful data fetch', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);

    await waitFor(() => {
      expect(screen.getByText('Projects & Skills Management')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Verify projects are displayed
    expect(screen.getByText('Project Alpha')).toBeInTheDocument();
    expect(screen.getByText('Project Beta')).toBeInTheDocument();

    // Verify skills are displayed
    expect(screen.getByText('Frontend Development')).toBeInTheDocument();
    expect(screen.getByText('Backend Development')).toBeInTheDocument();

    // Verify counts
    expect(screen.getByText(/Projects Management \(2\)/i)).toBeInTheDocument();
    expect(screen.getByText(/Skills Management \(2\)/i)).toBeInTheDocument();
  });

  // Test 3: Search functionality
  test('3. Filters projects and skills based on search input', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Project Alpha')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Search for React
    const searchInput = screen.getByPlaceholderText(/search projects or skills/i);
    await user.clear(searchInput);
    await user.type(searchInput, 'React');

    await waitFor(() => {
      expect(screen.getByText('Project Alpha')).toBeInTheDocument();
      expect(screen.getByText('Frontend Development')).toBeInTheDocument();
      expect(screen.queryByText('Backend Development')).not.toBeInTheDocument();
    }, { timeout: 3000 });
  });

  // Test 4: Opens create project modal
  test('4. Opens create project modal when button is clicked', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Project Alpha')).toBeInTheDocument();
    }, { timeout: 3000 });

    const createProjectButton = screen.getByText(/✨ Create New Project/i);
    await user.click(createProjectButton);

    await waitFor(() => {
      expect(screen.getByText('Create New Project')).toBeInTheDocument();
      expect(screen.getByPlaceholderText('Enter project name')).toBeInTheDocument();
    }, { timeout: 3000 });
  });

  // Test 5: Creates a new project
  test('5. Creates a new project successfully', async () => {
    fetch.mockImplementation((url, options) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      if (url.includes('create/new/project') && options?.method === 'POST') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, message: 'Project created successfully' })
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Project Alpha')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Open modal
    const createButton = screen.getByText(/✨ Create New Project/i);
    await user.click(createButton);

    await waitFor(() => {
      expect(screen.getByText('Create New Project')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Fill form
    const nameInput = screen.getByPlaceholderText('Enter project name');
    const descriptionInput = screen.getByPlaceholderText('Describe the project...');

    await user.type(nameInput, 'New Test Project');
    await user.type(descriptionInput, 'This is a test project description');

    // Submit
    const submitButton = screen.getByText('✨ Create Project');
    await user.click(submitButton);

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('create/new/project'),
        expect.objectContaining({
          method: 'POST',
          headers: expect.objectContaining({
            'Authorization': 'Bearer mock-token'
          })
        })
      );
    }, { timeout: 3000 });
  });

  // Test 6: Opens create skill modal
  test('6. Opens create skill modal when button is clicked', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Frontend Development')).toBeInTheDocument();
    }, { timeout: 3000 });

    const createSkillButton = screen.getByText(/✨ Create New Skill/i);
    await user.click(createSkillButton);

    await waitFor(() => {
      expect(screen.getByText('Create New Skill')).toBeInTheDocument();
      expect(screen.getByPlaceholderText('Enter skill name')).toBeInTheDocument();
    }, { timeout: 3000 });
  });

  // Test 7: Creates a new skill
  test('7. Creates a new skill successfully', async () => {
    fetch.mockImplementation((url, options) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills/create') && options?.method === 'POST') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, message: 'Skill created successfully' })
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Frontend Development')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Open modal
    const createButton = screen.getByText(/✨ Create New Skill/i);
    await user.click(createButton);

    await waitFor(() => {
      expect(screen.getByText('Create New Skill')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Fill form
    const nameInput = screen.getByPlaceholderText('Enter skill name');
    const descriptionInput = screen.getByPlaceholderText('Describe the skill...');
    const techInput = screen.getByPlaceholderText(/React, JavaScript/i);

    await user.type(nameInput, 'New Skill');
    await user.type(descriptionInput, 'Test skill description');
    await user.type(techInput, 'React, TypeScript');

    // Submit
    const submitButton = screen.getByText('✨ Create Skill');
    await user.click(submitButton);

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('skills/create'),
        expect.objectContaining({
          method: 'POST'
        })
      );
    }, { timeout: 3000 });
  });

  // Test 8: Opens edit project modal
  test('8. Opens edit project modal when edit button is clicked', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Project Alpha')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Find and click edit button
    const editButtons = screen.getAllByTitle('Edit project');
    await user.click(editButtons[0]);

    await waitFor(() => {
      expect(screen.getByText('Edit Project')).toBeInTheDocument();
      expect(screen.getByDisplayValue('Project Alpha')).toBeInTheDocument();
    }, { timeout: 3000 });
  });

  // Test 9: Deletes a project
  test('9. Deletes a project successfully', async () => {
    fetch.mockImplementation((url, options) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      if (url.includes('delete/project') && options?.method === 'DELETE') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true })
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Project Alpha')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Find and click delete button
    const deleteButtons = screen.getAllByTitle('Delete project');
    await user.click(deleteButtons[0]);

    await waitFor(() => {
      expect(window.confirm).toHaveBeenCalled();
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('delete/project/1'),
        expect.objectContaining({
          method: 'DELETE'
        })
      );
    }, { timeout: 3000 });
  });

  // Test 10: Deletes a skill
  test('10. Deletes a skill successfully', async () => {
    fetch.mockImplementation((url, options) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills/delete') && options?.method === 'DELETE') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, message: 'Skill deleted' })
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Frontend Development')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Find and click delete button
    const deleteButtons = screen.getAllByTitle('Delete skill');
    await user.click(deleteButtons[0]);

    await waitFor(() => {
      expect(window.confirm).toHaveBeenCalled();
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('skills/delete/1'),
        expect.objectContaining({
          method: 'DELETE'
        })
      );
    }, { timeout: 3000 });
  });

  // Test 11: Updates a project
  test('11. Updates a project successfully', async () => {
    fetch.mockImplementation((url, options) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      if (url.includes('modify/project') && options?.method === 'PUT') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, message: 'Project updated' })
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Project Alpha')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Open edit modal
    const editButtons = screen.getAllByTitle('Edit project');
    await user.click(editButtons[0]);

    await waitFor(() => {
      expect(screen.getByText('Edit Project')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Modify name
    const nameInput = screen.getByDisplayValue('Project Alpha');
    await user.clear(nameInput);
    await user.type(nameInput, 'Updated Project Alpha');

    // Submit
    const updateButton = screen.getByText('💾 Update Project');
    await user.click(updateButton);

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('modify/project/1'),
        expect.objectContaining({
          method: 'PUT'
        })
      );
    }, { timeout: 3000 });
  });

  // Test 12: Updates a skill
  test('12. Updates a skill successfully', async () => {
    fetch.mockImplementation((url, options) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills/update') && options?.method === 'PUT') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, message: 'Skill updated' })
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Frontend Development')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Open edit modal
    const editButtons = screen.getAllByTitle('Edit skill');
    await user.click(editButtons[0]);

    await waitFor(() => {
      expect(screen.getByText('Edit Skill')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Modify name
    const nameInput = screen.getByDisplayValue('Frontend Development');
    await user.clear(nameInput);
    await user.type(nameInput, 'Updated Frontend');

    // Submit
    const updateButton = screen.getByText('💾 Update Skill');
    await user.click(updateButton);

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('skills/update/1'),
        expect.objectContaining({
          method: 'PUT'
        })
      );
    }, { timeout: 3000 });
  });

  // Test 13: Shows empty states
  test('13. Shows empty states when no projects or skills exist', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);

    await waitFor(() => {
      expect(screen.getAllByText('No projects available')).toHaveLength(1);
      expect(screen.getByText('No skills available')).toBeInTheDocument();
    }, { timeout: 3000 });
  });

  // Test 14: Closes modal when clicking outside
  test('14. Closes modal when clicking on overlay', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Project Alpha')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Open modal
    const createButton = screen.getByText(/✨ Create New Project/i);
    await user.click(createButton);

    await waitFor(() => {
      expect(screen.getByText('Create New Project')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Click on overlay
    const overlay = document.querySelector('.modalOverlay');
    await user.click(overlay);

    await waitFor(() => {
      expect(screen.queryByText('Create New Project')).not.toBeInTheDocument();
    }, { timeout: 3000 });
  });

  // Test 15: Validates required fields
  test('15. Shows validation message for required fields', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('allprojects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects)
        });
      }
      if (url.includes('skills')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills)
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    render(<Projects />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Project Alpha')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Open modal
    const createButton = screen.getByText(/✨ Create New Project/i);
    await user.click(createButton);

    await waitFor(() => {
      expect(screen.getByText('Create New Project')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Try to submit without filling required fields
    const submitButton = screen.getByText('✨ Create Project');
    await user.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/name and description are required/i)).toBeInTheDocument();
    }, { timeout: 3000 });
  });
});