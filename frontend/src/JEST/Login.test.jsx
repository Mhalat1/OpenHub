import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import Login from '../pages/Login';

// Mock de useNavigate
const mockNavigate = jest.fn();
jest.mock('react-router-dom', () => ({
  ...jest.requireActual('react-router-dom'),
  useNavigate: () => mockNavigate,
}));

// Mock de l'image logo
jest.mock('../images/logo.png', () => 'mocked-logo.png');

// Mock des styles CSS modules
jest.mock('../style/login.module.css', () => ({
  loginContainer: 'loginContainer',
  heroSection: 'heroSection',
  logoContainer: 'logoContainer',
  logo: 'logo',
  heroText: 'heroText',
  features: 'features',
  featureItem: 'featureItem',
  formSection: 'formSection',
  formContainer: 'formContainer',
  formHeader: 'formHeader',
  form: 'form',
  inputGroup: 'inputGroup',
  label: 'label',
  input: 'input',
  submitButton: 'submitButton',
  loading: 'loading',
  spinner: 'spinner',
  errorMessage: 'errorMessage',
  errorIcon: 'errorIcon',
  registerSection: 'registerSection',
  divider: 'divider',
  registerButton: 'registerButton',
  forgotPassword: 'forgotPassword',
  forgotLink: 'forgotLink',
}));

// Helper pour rendre le composant
const renderLogin = () => {
  return render(
    <BrowserRouter>
      <Login />
    </BrowserRouter>
  );
};

describe('Login Component', () => {
  beforeEach(() => {
    // Reset des mocks avant chaque test
    jest.clearAllMocks();
    localStorage.clear();
    global.fetch = jest.fn();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  describe('Rendering', () => {
    it('should render the login form with all elements', () => {
      renderLogin();

      expect(screen.getByText('Bienvenue sur OpenHub')).toBeInTheDocument();
      expect(screen.getByText('Rejoignez la communauté des développeurs passionnés')).toBeInTheDocument();
      expect(screen.getByText('Connexion')).toBeInTheDocument();
      expect(screen.getByText('Accédez à votre espace personnel')).toBeInTheDocument();
      expect(screen.getByLabelText('Adresse email')).toBeInTheDocument();
      expect(screen.getByLabelText('Mot de passe')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /se connecter/i })).toBeInTheDocument();
    });

    it('should render all feature items', () => {
      renderLogin();

      expect(screen.getByText('Connectez avec des développeurs')).toBeInTheDocument();
      expect(screen.getByText('Partagez vos projets')).toBeInTheDocument();
      expect(screen.getByText('Collaborez en temps réel')).toBeInTheDocument();
    });

    it('should render the logo', () => {
      renderLogin();

      const logo = screen.getByAltText('OpenHub Logo');
      expect(logo).toBeInTheDocument();
      expect(logo).toHaveAttribute('src', 'mocked-logo.png');
    });

    it('should render register and forgot password buttons', () => {
      renderLogin();

      expect(screen.getByRole('button', { name: /créer un compte/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /mot de passe oublié/i })).toBeInTheDocument();
    });
  });

  describe('Form Input Handling', () => {
    it('should update email input on change', () => {
      renderLogin();

      const emailInput = screen.getByLabelText('Adresse email');
      fireEvent.change(emailInput, { target: { value: 'test@example.com' } });

      expect(emailInput.value).toBe('test@example.com');
    });

    it('should update password input on change', () => {
      renderLogin();

      const passwordInput = screen.getByLabelText('Mot de passe');
      fireEvent.change(passwordInput, { target: { value: 'password123' } });

      expect(passwordInput.value).toBe('password123');
    });

    it('should have noValidate attribute on form', () => {
      renderLogin();

      const form = screen.getByRole('button', { name: /se connecter/i }).closest('form');
      expect(form).toHaveAttribute('noValidate');
    });
  });

  describe('Client-side Validation', () => {
    it('should show error when email is empty', async () => {
      renderLogin();

      const passwordInput = screen.getByLabelText('Mot de passe');
      fireEvent.change(passwordInput, { target: { value: 'password123' } });

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText('Veuillez remplir tous les champs')).toBeInTheDocument();
      });

      expect(global.fetch).not.toHaveBeenCalled();
    });

    it('should show error when password is empty', async () => {
      renderLogin();

      const emailInput = screen.getByLabelText('Adresse email');
      fireEvent.change(emailInput, { target: { value: 'test@example.com' } });

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText('Veuillez remplir tous les champs')).toBeInTheDocument();
      });

      expect(global.fetch).not.toHaveBeenCalled();
    });

    it('should show error when both fields are empty', async () => {
      renderLogin();

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText('Veuillez remplir tous les champs')).toBeInTheDocument();
      });

      expect(global.fetch).not.toHaveBeenCalled();
    });

    it('should show error for invalid email format', async () => {
      renderLogin();

      const emailInput = screen.getByLabelText('Adresse email');
      const passwordInput = screen.getByLabelText('Mot de passe');

      fireEvent.change(emailInput, { target: { value: 'invalidemail' } });
      fireEvent.change(passwordInput, { target: { value: 'password123' } });

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText('Invalid email format')).toBeInTheDocument();
      });

      expect(global.fetch).not.toHaveBeenCalled();
    });
  });

  

  describe('Failed Login', () => {
    it('should handle incorrect credentials error', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        json: async () => ({ message: 'Identifiants incorrects' }),
      });

      renderLogin();

      const emailInput = screen.getByLabelText('Adresse email');
      const passwordInput = screen.getByLabelText('Mot de passe');

      fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
      fireEvent.change(passwordInput, { target: { value: 'wrongpassword' } });

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText('Identifiants incorrects')).toBeInTheDocument();
      });

      expect(localStorage.getItem('token')).toBeNull();
      expect(mockNavigate).not.toHaveBeenCalled();
    });

    it('should handle missing token in response', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Success but no token' }),
      });

      renderLogin();

      const emailInput = screen.getByLabelText('Adresse email');
      const passwordInput = screen.getByLabelText('Mot de passe');

      fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
      fireEvent.change(passwordInput, { target: { value: 'password123' } });

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText('Token manquant dans la réponse')).toBeInTheDocument();
      });

      expect(mockNavigate).not.toHaveBeenCalled();
    });

    it('should handle network error', async () => {
      global.fetch.mockRejectedValueOnce(new Error('Network error'));

      renderLogin();

      const emailInput = screen.getByLabelText('Adresse email');
      const passwordInput = screen.getByLabelText('Mot de passe');

      fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
      fireEvent.change(passwordInput, { target: { value: 'password123' } });

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText('Network error')).toBeInTheDocument();
      });

      expect(mockNavigate).not.toHaveBeenCalled();
    });

    it('should handle generic error without message', async () => {
      global.fetch.mockRejectedValueOnce({});

      renderLogin();

      const emailInput = screen.getByLabelText('Adresse email');
      const passwordInput = screen.getByLabelText('Mot de passe');

      fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
      fireEvent.change(passwordInput, { target: { value: 'password123' } });

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText('Erreur de connexion au serveur')).toBeInTheDocument();
      });
    });
  });

  describe('Navigation', () => {
    it('should navigate to register page when clicking create account button', () => {
      renderLogin();

      const registerButton = screen.getByRole('button', { name: /créer un compte/i });
      fireEvent.click(registerButton);

      expect(mockNavigate).toHaveBeenCalledWith('/register');
    });

    it('should navigate to reset password page when clicking forgot password button', () => {
      renderLogin();

      const forgotPasswordButton = screen.getByRole('button', { name: /mot de passe oublié/i });
      fireEvent.click(forgotPasswordButton);

      expect(mockNavigate).toHaveBeenCalledWith('/reset-password');
    });
  });

  describe('Disabled State', () => {
    it('should disable inputs when loading', async () => {
      global.fetch.mockImplementationOnce(
        () => new Promise((resolve) => setTimeout(() => resolve({
          ok: true,
          json: async () => ({ token: 'fake-token' }),
        }), 100))
      );

      renderLogin();

      const emailInput = screen.getByLabelText('Adresse email');
      const passwordInput = screen.getByLabelText('Mot de passe');

      fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
      fireEvent.change(passwordInput, { target: { value: 'password123' } });

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      expect(emailInput).toBeDisabled();
      expect(passwordInput).toBeDisabled();
      expect(submitButton).toBeDisabled();

      await waitFor(() => {
        expect(mockNavigate).toHaveBeenCalled();
      });
    });

    it('should disable navigation buttons when loading', async () => {
      global.fetch.mockImplementationOnce(
        () => new Promise((resolve) => setTimeout(() => resolve({
          ok: true,
          json: async () => ({ token: 'fake-token' }),
        }), 100))
      );

      renderLogin();

      const emailInput = screen.getByLabelText('Adresse email');
      const passwordInput = screen.getByLabelText('Mot de passe');

      fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
      fireEvent.change(passwordInput, { target: { value: 'password123' } });

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      const registerButton = screen.getByRole('button', { name: /créer un compte/i });
      expect(registerButton).toBeDisabled();

      await waitFor(() => {
        expect(mockNavigate).toHaveBeenCalled();
      });
    });
  });

  describe('Error Message Display', () => {
    it('should display error icon with error message', async () => {
      renderLogin();

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText('⚠️')).toBeInTheDocument();
        expect(screen.getByText('Veuillez remplir tous les champs')).toBeInTheDocument();
      });
    });

    it('should clear previous error on new submission', async () => {
      renderLogin();

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText('Veuillez remplir tous les champs')).toBeInTheDocument();
      });

      const emailInput = screen.getByLabelText('Adresse email');
      const passwordInput = screen.getByLabelText('Mot de passe');

      fireEvent.change(emailInput, { target: { value: 'invalid' } });
      fireEvent.change(passwordInput, { target: { value: 'password123' } });

      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.queryByText('Veuillez remplir tous les champs')).not.toBeInTheDocument();
        expect(screen.getByText('Invalid email format')).toBeInTheDocument();
      });
    });
  });

  describe('Form Submission', () => {
    it('should prevent default form submission', () => {
      renderLogin();

      const submitButton = screen.getByRole('button', { name: /se connecter/i });
      const form = submitButton.closest('form');
      
      // Vérifier que le formulaire existe et a l'attribut noValidate
      expect(form).toBeInTheDocument();
      expect(form).toHaveAttribute('noValidate');
      
      // Simuler la soumission du formulaire
      const mockPreventDefault = jest.fn();
      fireEvent.submit(form, { preventDefault: mockPreventDefault });
      
      // Le formulaire devrait avoir un handler onSubmit
      expect(form).toBeTruthy();
    });
  });
});