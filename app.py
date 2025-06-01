# app.py
from flask import Flask, request, jsonify
from flask_cors import CORS
from flask_sqlalchemy import SQLAlchemy
from datetime import datetime
import logging

app = Flask(__name__)
CORS(app)

# Configuration
app.config['SQLALCHEMY_DATABASE_URI'] = 'mysql+pymysql://root:@localhost/chatbot'
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
db = SQLAlchemy(app)

# Logger
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Modèles
class ChatSession(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    user_id = db.Column(db.String(50))
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

class Message(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    session_id = db.Column(db.Integer, db.ForeignKey('chat_session.id'), nullable=False)
    sender = db.Column(db.String(20))  # 'user' ou 'bot'
    content = db.Column(db.Text, nullable=False)
    timestamp = db.Column(db.DateTime, default=datetime.utcnow)

# Créer les tables
with app.app_context():
    db.create_all()

# Fonction de réponse simple
def generate_bot_response(message: str) -> str:
    message_lower = message.lower()

    if "programme" in message_lower or "formation" in message_lower:
        return "Nous offrons des formations telles que Bachelor (Informatique, Data, Droit), BBA, MBA, etc."
    elif "prix" in message_lower or "coût" in message_lower:
        return "Le coût dépend du programme choisi, mais commence à partir de 150.000 FCFA/mois."
    elif "bonjour" in message_lower or "salut" in message_lower:
        return "Bonjour, je suis Coumba, chatbot de l'IAM. Comment puis-je vous aider ?"
    elif "contact" in message_lower:
        return "Vous pouvez nous contacter au +221 774762394 ou par email à info@iam.td."
    elif "horaire" in message_lower:
        return "Les horaires varient selon les programmes : matin, soir ou week-end."
    elif "inscription" in message_lower:
        return "Vous pouvez vous inscrire en ligne ou directement dans notre campus à N'Djamena."
    elif "avis" in message_lower or "témoignage" in message_lower:
        return "Nous avons de nombreux témoignages d'anciens élèves satisfaits. Vous pouvez les consulter sur notre site web."
    elif "aide" in message_lower or "assistance" in message_lower:
        return "Je suis là pour vous aider ! Que puis-je faire pour vous ?" 
    elif "merci" in message_lower or "merci beaucoup" in message_lower: 
        return "De rien ! Si vous avez d'autres questions, n'hésitez pas à demander."
    elif "au revoir" in message_lower or "à bientôt" in message_lower:
        return "Au revoir ! N'hésitez pas à revenir si vous avez d'autres questions."
    elif "qui es-tu" in message_lower or "présente-toi" in message_lower:
        return "Je suis Coumba, le chatbot de l'IAM. Je suis ici pour vous aider avec vos questions sur nos formations et services."
    elif "problème" in message_lower or "bug" in message_lower:
        return "Je suis désolé d'apprendre que vous rencontrez un problème. Pouvez-vous me donner plus de détails ?"
    elif "recherche" in message_lower or "emploi" in message_lower:
        return "Nous avons un service d'accompagnement à l'emploi pour nos diplômés. Vous pouvez consulter notre site pour plus d'infos."
    elif "avis" in message_lower or "témoignage" in message_lower:
        return "Nous avons de nombreux témoignages d'anciens élèves satisfaits. Vous pouvez les consulter sur notre site web."
    elif "faq" in message_lower or "questions fréquentes" in message_lower:
        return "Vous pouvez consulter notre FAQ sur notre site web pour des réponses aux questions fréquentes."
    elif "partenariat" in message_lower or "collaboration" in message_lower:
        return "Nous sommes ouverts aux partenariats avec d'autres institutions. Contactez-nous pour plus d'infos."
    elif "bourse" in message_lower or "aide financière" in message_lower:
        return "Nous offrons des bourses d'études basées sur le mérite. Consultez notre site pour plus de détails."
    elif "événements" in message_lower or "activités" in message_lower:
        return "Nous organisons régulièrement des événements et des ateliers. Consultez notre site pour le calendrier."
    elif "réseaux sociaux" in message_lower or "suivez-nous" in message_lower:
        return "Vous pouvez nous suivre sur nos réseaux sociaux : Facebook, Twitter, Instagram pour les dernières nouvelles."
    elif "localisation" in message_lower or "adresse" in message_lower:
        return "Nous sommes situés à Dakar, au Senegale. Consultez notre site pour l'adresse exacte."
    elif "langues" in message_lower or "langue" in message_lower:
        return "Nous proposons des cours en français et en anglais. Vous pouvez choisir la langue de votre formation."

    else:
        return "Je vous écoute 😊 Vous pouvez me poser des questions sur les formations, prix, inscriptions, etc."

# Endpoint principal
@app.route("/chat", methods=["POST"])
def chat():
    try:
        data = request.get_json()
        user_id = data.get("user_id", "guest")
        user_message = data.get("message", "").strip()
        session_id = data.get("session_id")

        if not user_message:
            return jsonify({"error": "Message vide"}), 400

        # Créer une nouvelle session si aucune session_id fournie
        if not session_id:
            session = ChatSession(user_id=user_id)
            db.session.add(session)
            db.session.commit()
        else:
            session = ChatSession.query.get(session_id)
            if not session:
                return jsonify({"error": "Session introuvable"}), 404

        # Sauvegarder le message utilisateur
        db.session.add(Message(session_id=session.id, sender='user', content=user_message))

        # Générer la réponse du bot
        bot_response = generate_bot_response(user_message)

        # Sauvegarder la réponse du bot
        db.session.add(Message(session_id=session.id, sender='bot', content=bot_response))
        db.session.commit()

        return jsonify({
            "response": bot_response,
            "session_id": session.id
        })

    except Exception as e:
        logger.error(f"Erreur lors du traitement du chat : {str(e)}")
        return jsonify({"error": "Erreur interne du serveur."}), 500

# Endpoint pour récupérer l'historique d'un utilisateur
@app.route("/history", methods=["GET"])
def history():
    try:
        user_id = request.args.get("user_id")
        if not user_id:
            return jsonify({"error": "user_id manquant"}), 400

        sessions = ChatSession.query.filter_by(user_id=user_id).all()
        data = []
        for session in sessions:
            messages = Message.query.filter_by(session_id=session.id).order_by(Message.timestamp).all()
            data.append({
                "session_id": session.id,
                "created_at": session.created_at.isoformat(),
                "messages": [
                    {
                        "sender": m.sender,
                        "content": m.content,
                        "timestamp": m.timestamp.isoformat()
                    } for m in messages
                ]
            })

        return jsonify(data)
    except Exception as e:
        logger.error(f"Erreur lors de la récupération de l'historique : {str(e)}")
        return jsonify({"error": "Erreur serveur"}), 500

# Lancement de l'app
if __name__ == "__main__":
    app.run(debug=True)
