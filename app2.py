from flask import Flask, request, jsonify, render_template, send_from_directory
from flask_cors import CORS
from flask_sqlalchemy import SQLAlchemy
from werkzeug.security import generate_password_hash, check_password_hash
from datetime import datetime
import os
import re
import logging
from functools import wraps
import jwt
import uuid

# Configuration de base
app = Flask(__name__)
CORS(app)
app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///chat.db'
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
app.config['SECRET_KEY'] = 'votre_cle_secrete_a_changer'
app.config['UPLOAD_FOLDER'] = 'uploads'
app.config['MAX_CONTENT_LENGTH'] = 16 * 1024 * 1024  # 16MB max pour les fichiers

# Assurez-vous que le dossier d'upload existe
if not os.path.exists(app.config['UPLOAD_FOLDER']):
    os.makedirs(app.config['UPLOAD_FOLDER'])

# Configuration du logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler("app.log"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Initialisation de la base de données
db = SQLAlchemy(app)

# Modèles de base de données
class User(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    public_id = db.Column(db.String(50), unique=True)
    username = db.Column(db.String(50), unique=True)
    email = db.Column(db.String(100), unique=True)
    password = db.Column(db.String(100))
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    is_admin = db.Column(db.Boolean, default=False)
    sessions = db.relationship('ChatSession', backref='user', lazy=True)

class ChatSession(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.Integer, db.ForeignKey('user.id'))
    title = db.Column(db.String(100), default="Nouvelle conversation")
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    messages = db.relationship('Message', backref='session', lazy=True, cascade="all, delete-orphan")

class Message(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    session_id = db.Column(db.Integer, db.ForeignKey('chat_session.id', ondelete='CASCADE'))
    sender = db.Column(db.String(20))  # 'user' ou 'bot'
    content = db.Column(db.Text)
    timestamp = db.Column(db.DateTime, default=datetime.utcnow)
    attachment_path = db.Column(db.String(255), nullable=True)

class Education(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100), unique=True)
    description = db.Column(db.Text)
    level = db.Column(db.String(50))  # 'Bachelor', 'Master', etc.
    duration = db.Column(db.String(50))
    price = db.Column(db.Float)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

class FAQ(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    question = db.Column(db.String(200), unique=True)
    answer = db.Column(db.Text)
    category = db.Column(db.String(50))
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

# Créer les tables si elles n'existent pas
with app.app_context():
    db.create_all()
    
    # Ajouter quelques programmes éducatifs si la table est vide
    if Education.query.count() == 0:
        programs = [
            Education(
                name="Bachelor en Informatique", 
                description="Programme de trois ans couvrant les fondamentaux de l'informatique, la programmation et le développement logiciel.", 
                level="Bachelor", 
                duration="3 ans", 
                price=150000.0
            ),
            Education(
                name="Bachelor en Data Science", 
                description="Formation sur l'analyse de données, le machine learning et la visualisation.", 
                level="Bachelor", 
                duration="3 ans", 
                price=175000.0
            ),
            Education(
                name="Bachelor en Droit du Numérique", 
                description="Études juridiques spécialisées dans les aspects légaux des technologies.", 
                level="Bachelor", 
                duration="3 ans", 
                price=160000.0
            ),
            Education(
                name="BBA en Management International", 
                description="Formation en gestion d'entreprise avec une dimension internationale.", 
                level="Bachelor", 
                duration="4 ans", 
                price=200000.0
            ),
            Education(
                name="Master en Cybersécurité", 
                description="Formation avancée en sécurité informatique et défense contre les cyberattaques.", 
                level="Master", 
                duration="2 ans", 
                price=250000.0
            )
        ]
        db.session.bulk_save_objects(programs)
        db.session.commit()
    
    # Ajouter quelques FAQs si la table est vide
    if FAQ.query.count() == 0:
        faqs = [
            FAQ(
                question="Quels programmes offrez-vous ?", 
                answer="Nous proposons des Bachelors en Informatique, Data Science, Droit du Numérique, un BBA en Management International et un Master en Cybersécurité.",
                category="Programmes"
            ),
            FAQ(
                question="Quel est le prix des formations ?", 
                answer="Nos tarifs varient entre 150.000 et 250.000 FCFA par mois selon le programme choisi.",
                category="Frais"
            ),
            FAQ(
                question="Comment s'inscrire ?", 
                answer="L'inscription se fait en ligne sur notre site ou directement à notre campus. Un dossier de candidature doit être soumis incluant vos relevés de notes, une lettre de motivation et une copie de votre pièce d'identité.",
                category="Admission"
            ),
            FAQ(
                question="Y a-t-il des bourses disponibles ?", 
                answer="Oui, nous offrons des bourses d'excellence et des bourses sociales. Le dossier de demande doit être soumis avant le 31 mai pour l'année académique suivante.",
                category="Frais"
            ),
            FAQ(
                question="Où est situé votre campus ?", 
                answer="Notre campus principal est situé au centre-ville de Dakar, à proximité du Plateau. Nous avons également un campus secondaire à Saly.",
                category="Général"
            )
        ]
        db.session.bulk_save_objects(faqs)
        db.session.commit()

# Fonctions utilitaires
def token_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        token = None
        
        if 'Authorization' in request.headers:
            auth_header = request.headers['Authorization']
            if auth_header.startswith('Bearer '):
                token = auth_header.split(' ')[1]
        
        if not token:
            return jsonify({'message': 'Token manquant!'}), 401
        
        try:
            data = jwt.decode(token, app.config['SECRET_KEY'], algorithms=["HS256"])
            current_user = User.query.filter_by(public_id=data['public_id']).first()
        except:
            return jsonify({'message': 'Token invalide!'}), 401
        
        return f(current_user, *args, **kwargs)
    
    return decorated

def allowed_file(filename):
    ALLOWED_EXTENSIONS = {'txt', 'pdf', 'png', 'jpg', 'jpeg', 'gif', 'doc', 'docx'}
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def get_bot_response(message, session_id=None):
    """
    Génère une réponse intelligente basée sur le message de l'utilisateur
    en cherchant dans la base de connaissances.
    """
    message_lower = message.lower()
    
    # Recherche sur les programmes éducatifs
    if re.search(r'\b(programme|formation|étude|bachelor|master|bba|diplôme)\b', message_lower):
        programs = Education.query.all()
        response = "Voici nos programmes disponibles:\n\n"
        for program in programs:
            response += f"- {program.name}: {program.description}\n  Durée: {program.duration}, Prix: {program.price:,.0f} FCFA/mois\n\n"
        return response
    
    # Recherche sur les prix
    elif re.search(r'\b(prix|tarif|coût|cout|combien|frais)\b', message_lower):
        response = "Voici nos tarifs pour les différentes formations:\n\n"
        programs = Education.query.all()
        for program in programs:
            response += f"- {program.name}: {program.price:,.0f} FCFA/mois\n"
        return response
    
    # Salutations
    elif re.search(r'\b(bonjour|salut|coucou|hello|hi|hey|salam|bonsoir)\b', message_lower):
        return "Bonjour! Je suis Coumba, le chatbot de l'IAM. Comment puis-je vous aider aujourd'hui? Vous pouvez me poser des questions sur nos programmes, nos tarifs, l'admission ou toute autre information concernant notre institut."
    
    # Questions d'admission
    elif re.search(r'\b(admission|inscription|candidature|postuler|s\'inscrire)\b', message_lower):
        return "Pour vous inscrire, vous devez soumettre un dossier comprenant vos relevés de notes, une lettre de motivation et une copie de votre pièce d'identité. Vous pouvez vous inscrire en ligne sur notre site web ou vous rendre directement sur notre campus. Souhaitez-vous plus d'informations sur un programme spécifique?"
    
    # Recherche dans la FAQ
    else:
        # Recherche de mots-clés dans la FAQ
        faqs = FAQ.query.all()
        for faq in faqs:
            # Convertir la question en minuscules et chercher des mots-clés communs
            question_lower = faq.question.lower()
            keywords = set(question_lower.split()) & set(message_lower.split())
            if len(keywords) >= 2:  # Si au moins deux mots-clés correspondent
                return faq.answer
    
    # Réponse par défaut
    return "Je n'ai pas toutes les informations nécessaires pour répondre à cette question spécifique. Pouvez-vous reformuler votre question ou me demander des informations sur nos programmes, nos tarifs ou l'admission? Vous pouvez également contacter notre équipe d'admission au +221 33 456 7890 pour plus de détails."

# Routes pour l'interface web
@app.route('/')
def index():
    return render_template('index.html')

@app.route('/uploads/<path:filename>')
def uploaded_file(filename):
    return send_from_directory(app.config['UPLOAD_FOLDER'], filename)

# Routes API

@app.route("/api/register", methods=["POST"])
def register():
    data = request.get_json()
    
    # Validation des données
    if not data or not data.get('username') or not data.get('email') or not data.get('password'):
        return jsonify({'message': 'Données incomplètes'}), 400
    
    # Vérifier si l'utilisateur existe déjà
    if User.query.filter_by(username=data['username']).first():
        return jsonify({'message': 'Nom d\'utilisateur déjà utilisé'}), 409
    
    if User.query.filter_by(email=data['email']).first():
        return jsonify({'message': 'Email déjà utilisé'}), 409
    
    # Créer nouvel utilisateur
    hashed_password = generate_password_hash(data['password'], method='pbkdf2:sha256')
    new_user = User(
        public_id=str(uuid.uuid4()),
        username=data['username'],
        email=data['email'],
        password=hashed_password
    )
    
    db.session.add(new_user)
    db.session.commit()
    
    return jsonify({'message': 'Utilisateur créé avec succès!'}), 201

@app.route("/api/login", methods=["POST"])
def login():
    data = request.get_json()
    
    if not data or (not data.get('username') and not data.get('email')) or not data.get('password'):
        return jsonify({'message': 'Données d\'authentification manquantes'}), 400
    
    # Chercher l'utilisateur par nom d'utilisateur ou email
    user = None
    if data.get('username'):
        user = User.query.filter_by(username=data['username']).first()
    elif data.get('email'):
        user = User.query.filter_by(email=data['email']).first()
    
    if not user:
        return jsonify({'message': 'Utilisateur non trouvé'}), 404
    
    if check_password_hash(user.password, data['password']):
        # Générer le token
        token = jwt.encode({
            'public_id': user.public_id,
            'exp': datetime.utcnow() + datetime.timedelta(days=7)
        }, app.config['SECRET_KEY'], algorithm="HS256")
        
        return jsonify({
            'message': 'Connexion réussie',
            'token': token,
            'user_id': user.id,
            'username': user.username
        }), 200
    
    return jsonify({'message': 'Mot de passe incorrect'}), 401

@app.route("/api/chat", methods=["POST"])
@token_required
def chat(current_user):
    data = request.get_json()
    message = data.get("message")
    session_id = data.get("session_id")
    
    if not message:
        return jsonify({'message': 'Le message ne peut pas être vide'}), 400
    
    # Créer une nouvelle session si nécessaire
    if not session_id:
        session = ChatSession(user_id=current_user.id)
        db.session.add(session)
        db.session.commit()
        session_id = session.id
    else:
        session = ChatSession.query.filter_by(id=session_id, user_id=current_user.id).first()
        if not session:
            return jsonify({'message': 'Session non trouvée ou non autorisée'}), 404
    
    # Mettre à jour le timestamp de la session
    session.updated_at = datetime.utcnow()
    
    # Enregistrer le message de l'utilisateur
    user_message = Message(session_id=session.id, sender='user', content=message)
    db.session.add(user_message)
    
    # Générer et enregistrer la réponse du bot
    bot_response = get_bot_response(message, session_id)
    bot_message = Message(session_id=session.id, sender='bot', content=bot_response)
    db.session.add(bot_message)
    
    # Mettre à jour le titre de la session basé sur le premier message si c'est une nouvelle session
    if len(session.messages) <= 2:  # Seulement les 2 messages que nous venons d'ajouter
        # Créer un titre à partir du premier message utilisateur
        title = message[:50] + "..." if len(message) > 50 else message
        session.title = title
    
    db.session.commit()
    
    return jsonify({
        "response": bot_response,
        "session_id": session.id,
        "timestamp": bot_message.timestamp.isoformat()
    })

@app.route("/api/chat/upload", methods=["POST"])
@token_required
def upload_file(current_user):
    session_id = request.form.get("session_id")
    
    # Vérifier si la session existe et appartient à l'utilisateur
    if session_id:
        session = ChatSession.query.filter_by(id=session_id, user_id=current_user.id).first()
        if not session:
            return jsonify({'message': 'Session non trouvée ou non autorisée'}), 404
    else:
        # Créer une nouvelle session
        session = ChatSession(user_id=current_user.id)
        db.session.add(session)
        db.session.commit()
        session_id = session.id
    
    # Vérifier si un fichier a été envoyé
    if 'file' not in request.files:
        return jsonify({'message': 'Aucun fichier envoyé'}), 400
    
    file = request.files['file']
    
    if file.filename == '':
        return jsonify({'message': 'Aucun fichier sélectionné'}), 400
    
    if file and allowed_file(file.filename):
        # Sécuriser le nom du fichier
        filename = str(uuid.uuid4()) + "_" + os.path.basename(file.filename)
        file_path = os.path.join(app.config['UPLOAD_FOLDER'], filename)
        file.save(file_path)
        
        # Enregistrer le message avec pièce jointe
        message_content = f"[Fichier attaché: {os.path.basename(file.filename)}]"
        message = Message(
            session_id=session.id, 
            sender='user', 
            content=message_content,
            attachment_path=filename
        )
        db.session.add(message)
        
        # Réponse du bot pour le fichier
        bot_response = f"J'ai bien reçu votre fichier: {os.path.basename(file.filename)}. Comment puis-je vous aider à ce sujet?"
        bot_message = Message(session_id=session.id, sender='bot', content=bot_response)
        db.session.add(bot_message)
        
        db.session.commit()
        
        return jsonify({
            'message': 'Fichier téléchargé avec succès',
            'file_url': f'/uploads/{filename}',
            'session_id': session_id,
            'response': bot_response
        }), 201
    
    return jsonify({'message': 'Type de fichier non autorisé'}), 400

@app.route("/api/sessions", methods=["GET"])
@token_required
def get_sessions(current_user):
    sessions = ChatSession.query.filter_by(user_id=current_user.id).order_by(ChatSession.updated_at.desc()).all()
    
    result = []
    for session in sessions:
        # Obtenir le premier message pour afficher un aperçu
        first_message = Message.query.filter_by(session_id=session.id, sender='user').order_by(Message.timestamp.asc()).first()
        preview = first_message.content if first_message else "Nouvelle conversation"
        
        result.append({
            "id": session.id,
            "title": session.title,
            "created_at": session.created_at.isoformat(),
            "updated_at": session.updated_at.isoformat(),
            "preview": preview[:100] + "..." if len(preview) > 100 else preview
        })
    
    return jsonify(result)

@app.route("/api/sessions/<int:session_id>", methods=["GET"])
@token_required
def get_session_messages(current_user, session_id):
    session = ChatSession.query.filter_by(id=session_id, user_id=current_user.id).first()
    
    if not session:
        return jsonify({'message': 'Session non trouvée ou non autorisée'}), 404
    
    messages = Message.query.filter_by(session_id=session_id).order_by(Message.timestamp.asc()).all()
    
    result = {
        "session_id": session.id,
        "title": session.title,
        "created_at": session.created_at.isoformat(),
        "updated_at": session.updated_at.isoformat(),
        "messages": [{
            "id": msg.id,
            "sender": msg.sender,
            "content": msg.content,
            "timestamp": msg.timestamp.isoformat(),
            "attachment": f'/uploads/{msg.attachment_path}' if msg.attachment_path else None
        } for msg in messages]
    }
    
    return jsonify(result)

@app.route("/api/sessions/<int:session_id>", methods=["DELETE"])
@token_required
def delete_session(current_user, session_id):
    session = ChatSession.query.filter_by(id=session_id, user_id=current_user.id).first()
    
    if not session:
        return jsonify({'message': 'Session non trouvée ou non autorisée'}), 404
    
    # Supprimer les messages associés à la session
    Message.query.filter_by(session_id=session_id).delete()
    
    # Supprimer la session
    db.session.delete(session)
    db.session.commit()
    
    return jsonify({'message': 'Session supprimée avec succès'})

@app.route("/api/sessions/<int:session_id>", methods=["PUT"])
@token_required
def update_session(current_user, session_id):
    data = request.get_json()
    
    if not data or not data.get('title'):
        return jsonify({'message': 'Le titre ne peut pas être vide'}), 400
    
    session = ChatSession.query.filter_by(id=session_id, user_id=current_user.id).first()
    
    if not session:
        return jsonify({'message': 'Session non trouvée ou non autorisée'}), 404
    
    session.title = data['title']
    db.session.commit()
    
    return jsonify({'message': 'Titre de la session mis à jour avec succès'})

@app.route("/api/education", methods=["GET"])
def get_education_programs():
    programs = Education.query.all()
    
    result = [{
        "id": prog.id,
        "name": prog.name,
        "description": prog.description,
        "level": prog.level,
        "duration": prog.duration,
        "price": prog.price
    } for prog in programs]
    
    return jsonify(result)

@app.route("/api/faq", methods=["GET"])
def get_faq():
    category = request.args.get('category')
    
    query = FAQ.query
    if category:
        query = query.filter_by(category=category)
    
    faqs = query.all()
    
    result = [{
        "id": faq.id,
        "question": faq.question,
        "answer": faq.answer,
        "category": faq.category
    } for faq in faqs]
    
    return jsonify(result)

# Route pour les erreurs 404
@app.errorhandler(404)
def not_found(error):
    return jsonify({'message': 'Ressource non trouvée'}), 404

# Route pour les erreurs 500
@app.errorhandler(500)
def server_error(error):
    logger.error(f"Erreur interne: {str(error)}")
    return jsonify({'message': 'Erreur interne du serveur'}), 500
# Pour le développement local, ajouter des templates basiques
@app.route('/templates/<path:path>')
def serve_template(path):
    return send_from_directory('templates', path)

if __name__ == "__main__":
    app.run(debug=True, host='0.0.0.0', port=5000)

